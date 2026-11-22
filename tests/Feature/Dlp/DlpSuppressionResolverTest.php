<?php

namespace Tests\Feature\Dlp;

use App\Enums\DlpDisposition;
use App\Enums\DlpSuppressionState;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\DlpFindingResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\DlpFinding;
use App\Models\DlpPolicy;
use App\Models\DlpSuppression;
use App\Models\User;
use App\Services\Dlp\DlpQualityMetrics;
use App\Services\Dlp\DlpSuppressionResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DlpSuppressionResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_finds_an_in_force_suppression_for_the_finding(): void
    {
        $policy = DlpPolicy::factory()->create();
        $finding = $this->finding($policy);
        $suppression = DlpSuppression::factory()->active()->create([
            'dlp_policy_id' => $policy->getKey(),
            'identifier_type' => 'payment_card',
        ]);

        $this->assertSame($suppression->getKey(), $this->resolver()->suppressionFor($finding)?->getKey());
    }

    #[Test]
    public function it_ignores_a_suppression_that_is_not_in_force(): void
    {
        $policy = DlpPolicy::factory()->create();
        $finding = $this->finding($policy);

        DlpSuppression::factory()->create(['dlp_policy_id' => $policy->getKey()]); // requested
        DlpSuppression::factory()->expired()->create(['dlp_policy_id' => $policy->getKey()]);
        DlpSuppression::factory()->active()->create([
            'dlp_policy_id' => $policy->getKey(),
            'state' => DlpSuppressionState::Revoked,
        ]);

        $this->assertNull($this->resolver()->suppressionFor($finding));
    }

    #[Test]
    public function it_ignores_a_suppression_for_another_policy_or_scope(): void
    {
        $policy = DlpPolicy::factory()->create();
        $otherPolicy = DlpPolicy::factory()->create(['code' => 'DP-002']);
        $finding = $this->finding($policy);

        DlpSuppression::factory()->active()->create(['dlp_policy_id' => $otherPolicy->getKey()]);
        DlpSuppression::factory()->active()->create([
            'dlp_policy_id' => $policy->getKey(),
            'identifier_type' => 'email_address',
        ]);

        $this->assertNull($this->resolver()->suppressionFor($finding));
    }

    #[Test]
    public function forgetting_the_cache_picks_up_a_new_suppression(): void
    {
        $policy = DlpPolicy::factory()->create();
        $finding = $this->finding($policy);
        $resolver = $this->resolver();

        $this->assertNull($resolver->suppressionFor($finding));

        $suppression = DlpSuppression::factory()->active()->create(['dlp_policy_id' => $policy->getKey()]);

        $this->assertNull($resolver->suppressionFor($finding));

        $resolver->forget();

        $this->assertSame($suppression->getKey(), $resolver->suppressionFor($finding)?->getKey());
    }

    #[Test]
    public function the_quality_metrics_report_acceptance_coverage(): void
    {
        $policy = DlpPolicy::factory()->create();
        $suppression = DlpSuppression::factory()->active()->create(['dlp_policy_id' => $policy->getKey()]);

        $this->finding($policy);
        $this->finding($policy);

        $summary = app(DlpQualityMetrics::class)->summary();
        $row = collect($summary['rows'])->firstWhere('code', 'payment_card');

        $this->assertSame(2, $summary['reviewed']);
        $this->assertSame(2, $summary['suppressed']);
        $this->assertSame(2, $row['suppressed']);
        $this->assertSame('DS-'.str_pad((string) $suppression->getKey(), 4, '0', STR_PAD_LEFT), $suppression->reference());
    }

    #[Test]
    public function the_finding_queue_shows_the_accepting_suppression(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $policy = DlpPolicy::factory()->create();
        $this->finding($policy);
        $suppression = DlpSuppression::factory()->active()->create(['dlp_policy_id' => $policy->getKey()]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        $this->actingAs($admin)
            ->get(DlpFindingResource::getUrl('index'))
            ->assertOk()
            ->assertSee($suppression->reference());
    }

    protected function finding(DlpPolicy $policy): DlpFinding
    {
        return DlpFinding::query()->create([
            'source' => 'upload',
            'dlp_policy_id' => $policy->getKey(),
            'severity' => 'medium',
            'match_types' => ['Payment card number'],
            'identifier_types' => ['payment_card'],
            'action' => 'flagged',
            'disposition' => DlpDisposition::TruePositive,
            'reviewed_at' => now(),
            'content_sha256' => hash('sha256', uniqid('dlp-', true)),
        ]);
    }

    protected function resolver(): DlpSuppressionResolver
    {
        return app(DlpSuppressionResolver::class);
    }
}
