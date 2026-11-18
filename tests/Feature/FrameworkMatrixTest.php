<?php

namespace Tests\Feature;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Enums\UserRole;
use App\Filament\Pages\FrameworkMatrix;
use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceSource;
use App\Models\User;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FrameworkMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    #[Test]
    public function a_viewer_can_see_the_outcomes_a_framework_requires(): void
    {
        $required = $this->outcome('CTRL-ACCESS-001', 'Least-privilege access control');
        $other = $this->outcome('CTRL-TRAIN-001', 'Security awareness');

        $this->framework('aaa_framework', 'A Framework', [$required]);
        $this->framework('zzz_framework', 'Z Framework', [$other]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))
            ->get(FrameworkMatrix::getUrl())
            ->assertOk()
            ->assertSee('CTRL-ACCESS-001')
            ->assertSee(__('assurance.framework.help'))
            ->assertSee(__('assurance.score.heading'))
            ->assertSee(__('assurance.score.components.reviewed'))
            ->assertSee(__('assurance.score.no_trend'))
            ->assertDontSee('CTRL-TRAIN-001');
    }

    #[Test]
    public function switching_framework_changes_the_outcomes_on_the_page(): void
    {
        $first = $this->outcome('CTRL-ACCESS-001', 'Least-privilege access control');
        $second = $this->outcome('CTRL-TRAIN-001', 'Security awareness');

        $this->framework('aaa_framework', 'A Framework', [$first]);
        $this->framework('zzz_framework', 'Z Framework', [$second]);

        Livewire::actingAs(User::factory()->create(['role' => UserRole::Viewer]))
            ->test(FrameworkMatrix::class)
            ->assertSee('CTRL-ACCESS-001')
            ->set('framework', 'zzz_framework')
            ->assertSee('CTRL-TRAIN-001')
            ->assertDontSee('CTRL-ACCESS-001');
    }

    #[Test]
    public function status_says_what_is_missing_instead_of_scoring_compliance(): void
    {
        $evidenced = $this->outcome('CTRL-ACCESS-001', 'Least-privilege access control');
        $unevidenced = $this->outcome('CTRL-LOG-001', 'Logging and monitoring');
        $unlinked = $this->outcome('CTRL-TRAIN-001', 'Security awareness');

        $framework = $this->framework('aaa_framework', 'A Framework', [$evidenced, $unevidenced, $unlinked]);

        $linked = Control::factory()->create(['code' => 'AC-2']);
        $linked->canonicalOutcomes()->attach($evidenced->getKey(), ['source' => 'manual']);
        $this->evidenceFor($linked, 'AC-2');

        $needsEvidence = Control::factory()->create(['code' => 'AU-2']);
        $needsEvidence->canonicalOutcomes()->attach($unevidenced->getKey(), ['source' => 'manual']);

        Livewire::actingAs(User::factory()->create(['role' => UserRole::Viewer]))
            ->test(FrameworkMatrix::class)
            ->assertSee(__('assurance.status.covered'))
            ->assertSee(__('assurance.status.no_evidence'))
            ->assertSee(__('assurance.status.no_control'));

        $this->assertNotNull($framework);
    }

    #[Test]
    public function the_gaps_filter_keeps_only_outcomes_needing_review(): void
    {
        $covered = $this->outcome('CTRL-ACCESS-001', 'Least-privilege access control');
        $unlinked = $this->outcome('CTRL-TRAIN-001', 'Security awareness');

        $this->framework('aaa_framework', 'A Framework', [$covered, $unlinked]);

        $control = Control::factory()->create(['code' => 'AC-2']);
        $control->canonicalOutcomes()->attach($covered->getKey(), ['source' => 'manual']);
        $this->evidenceFor($control, 'AC-2');

        Livewire::actingAs(User::factory()->create(['role' => UserRole::Viewer]))
            ->test(FrameworkMatrix::class)
            ->filterTable('gaps', ['only_gaps' => true])
            ->assertSee('CTRL-TRAIN-001')
            ->assertDontSee('CTRL-ACCESS-001');
    }

    #[Test]
    public function without_a_catalogue_the_page_says_how_to_populate_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))
            ->get(FrameworkMatrix::getUrl())
            ->assertOk()
            ->assertSee(__('assurance.framework.none'));
    }

    private function outcome(string $code, string $name): CanonicalControl
    {
        return CanonicalControl::create([
            'code' => $code,
            'name' => $name,
            'priority' => 1,
        ]);
    }

    /**
     * @param  array<int, CanonicalControl>  $outcomes
     */
    private function framework(string $code, string $name, array $outcomes): CanonicalFramework
    {
        $framework = CanonicalFramework::create([
            'code' => $code,
            'name' => $name,
            'priority' => 1,
        ]);

        $framework->controls()->attach(array_map(fn (CanonicalControl $outcome) => $outcome->getKey(), $outcomes));

        return $framework;
    }

    private function evidenceFor(Control $control, string $controlIdentifier): void
    {
        $source = EvidenceSource::create([
            'name' => 'Test source',
            'type' => EvidenceSourceType::Other,
            'enabled' => true,
            'configuration' => [],
        ]);

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Queued,
        ]);

        app(EvidenceIngestor::class)->ingest(
            $source,
            $job,
            new NormalisedEvidence(
                externalId: 'host-1',
                evidenceType: 'test',
                status: ControlStatus::Effective,
                collectedAt: CarbonImmutable::parse('2026-11-18T09:00:00Z'),
                payload: ['ok' => true],
                controlIdentifier: $controlIdentifier,
            ),
        );
    }
}
