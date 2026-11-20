<?php

namespace Tests\Feature\Dlp;

use App\Enums\DlpDisposition;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\DlpFindingResource;
use App\Filament\Pages\DlpQuality;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\DlpFinding;
use App\Models\User;
use App\Services\Dlp\DlpFindingReview;
use App\Services\Dlp\DlpQualityMetrics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DlpQualityMetricsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_measures_false_positive_rate_by_identifier_type(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->finding('payment_card', ['Payment card number'], $i === 0 ? DlpDisposition::FalsePositive : DlpDisposition::TruePositive);
        }

        for ($i = 0; $i < 20; $i++) {
            $this->finding('email_address', ['Email address'], $i === 0 ? DlpDisposition::FalsePositive : DlpDisposition::TruePositive);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->finding('ssn', ['US social security number'], DlpDisposition::TruePositive);
        }

        $summary = app(DlpQualityMetrics::class)->summary();

        $card = collect($summary['rows'])->firstWhere('code', 'payment_card');
        $email = collect($summary['rows'])->firstWhere('code', 'email_address');
        $ssn = collect($summary['rows'])->firstWhere('code', 'ssn');

        $this->assertSame(20, $card['reviewed']);
        $this->assertSame(1, $card['false_positives']);
        $this->assertSame(5.0, $card['rate']);
        $this->assertSame(2.0, $card['target']);
        $this->assertSame('above_target', $card['status']);

        $this->assertSame('on_target', $email['status']);
        $this->assertSame('insufficient', $ssn['status']);
        $this->assertSame(45, $summary['reviewed']);
        $this->assertSame(2, $summary['false_positives']);
    }

    #[Test]
    public function benign_matches_are_not_false_positives(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->finding('aws_access_key', ['AWS access key'], $i === 0 ? DlpDisposition::Benign : DlpDisposition::TruePositive);
        }

        $summary = app(DlpQualityMetrics::class)->summary();
        $row = collect($summary['rows'])->firstWhere('code', 'aws_access_key');

        $this->assertSame(10, $row['reviewed']);
        $this->assertSame(0, $row['false_positives']);
        $this->assertSame(0.0, $row['rate']);
        $this->assertSame('on_target', $row['status']);
    }

    #[Test]
    public function a_review_records_the_reviewer_and_notes_without_changing_detection_metadata(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $finding = $this->finding('payment_card', ['Payment card number'], DlpDisposition::TruePositive);

        $reviewed = app(DlpFindingReview::class)->record(
            $finding,
            DlpDisposition::FalsePositive,
            'The number is a test card in a training dataset.',
            $admin,
        );

        $this->assertSame(DlpDisposition::FalsePositive, $reviewed->disposition);
        $this->assertSame($admin->getKey(), $reviewed->reviewed_by);
        $this->assertSame('The number is a test card in a training dataset.', $reviewed->review_notes);
        $this->assertNotNull($reviewed->reviewed_at);
        $this->assertSame(['Payment card number'], $reviewed->match_types);
        $this->assertSame(['payment_card'], $reviewed->identifier_types);
    }

    #[Test]
    public function the_quality_page_shows_identifier_targets_and_status(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        for ($i = 0; $i < 20; $i++) {
            $this->finding('payment_card', ['Payment card number'], $i === 0 ? DlpDisposition::FalsePositive : DlpDisposition::TruePositive);
        }

        $this->actingAs($viewer)
            ->get(DlpQuality::getUrl())
            ->assertOk()
            ->assertSee('DLP false-positive quality')
            ->assertSee('Payment card number')
            ->assertSee('Above target')
            ->assertSee('There is no single industry-standard false-positive percentage');
    }

    #[Test]
    public function an_admin_can_open_the_dlp_review_queue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->finding('payment_card', ['Payment card number'], DlpDisposition::TruePositive);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        $this->actingAs($admin)
            ->get(DlpFindingResource::getUrl('index'))
            ->assertOk()
            ->assertSee('DLP findings');
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function finding(string $code, array $labels, DlpDisposition $disposition): DlpFinding
    {
        return DlpFinding::query()->create([
            'source' => 'ai',
            'severity' => 'medium',
            'match_types' => $labels,
            'identifier_types' => [$code],
            'action' => 'flagged',
            'disposition' => $disposition,
            'reviewed_by' => null,
            'reviewed_at' => now(),
            'content_sha256' => hash('sha256', uniqid('dlp-', true)),
        ]);
    }
}
