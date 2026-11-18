<?php

namespace Tests\Feature;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Enums\UserRole;
use App\Filament\Resources\EvidenceRecordResource;
use App\Filament\Resources\PolicyResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\CanonicalControl;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\Policy;
use App\Models\User;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Policies link directly to outcomes; evidence inherits through its control.
 * Both expectations are stated in the UI so an empty state is not read as a bug.
 */
class CanonicalOutcomeCoverageUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function the_policy_form_explains_direct_and_indirect_support(): void
    {
        $policy = Policy::factory()->create();

        $this->actingAs($this->admin())
            ->get(PolicyResource::getUrl('edit', ['record' => $policy]))
            ->assertOk()
            ->assertSee(__('control.form.canonical_outcomes.policy_helper'));
    }

    #[Test]
    public function an_unlinked_policy_is_marked_as_a_coverage_gap(): void
    {
        $policy = Policy::factory()->create();

        $this->actingAs($this->admin())
            ->get(PolicyResource::getUrl('view-details', ['record' => $policy]))
            ->assertOk()
            ->assertSee(__('control.infolist.canonical_outcomes_policy_empty'));
    }

    #[Test]
    public function a_linked_policy_shows_its_outcomes(): void
    {
        $outcome = $this->outcome('CTRL-GOV-001');
        $policy = Policy::factory()->create();
        $policy->canonicalOutcomes()->attach($outcome->getKey(), ['source' => 'manual']);

        $this->actingAs($this->admin())
            ->get(PolicyResource::getUrl('view-details', ['record' => $policy]))
            ->assertOk()
            ->assertSee('CTRL-GOV-001');
    }

    #[Test]
    public function evidence_shows_the_outcomes_inherited_from_its_control(): void
    {
        $outcome = $this->outcome('CTRL-LOG-001');
        $control = Control::factory()->create(['code' => 'AU-2']);
        $control->canonicalOutcomes()->attach($outcome->getKey(), ['source' => 'manual']);

        $record = $this->evidenceFor($control, 'AU-2');

        $this->actingAs($this->admin())
            ->get(EvidenceRecordResource::getUrl('view', ['record' => $record]))
            ->assertOk()
            ->assertSee(__('evidence.entries.canonical_outcomes'))
            ->assertSee('CTRL-LOG-001')
            ->assertDontSee(__('evidence.entries.canonical_outcomes_none'));
    }

    #[Test]
    public function evidence_without_a_linked_control_says_it_has_no_outcome(): void
    {
        $control = Control::factory()->create(['code' => 'AU-2']);

        $record = $this->evidenceFor($control, 'AU-2');

        $this->actingAs($this->admin())
            ->get(EvidenceRecordResource::getUrl('view', ['record' => $record]))
            ->assertOk()
            ->assertSee(__('evidence.entries.canonical_outcomes_none'));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function outcome(string $code): CanonicalControl
    {
        return CanonicalControl::create([
            'code' => $code,
            'name' => $code,
            'priority' => 1,
        ]);
    }

    private function evidenceFor(Control $control, string $controlIdentifier): EvidenceRecord
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

        return app(EvidenceIngestor::class)->ingest(
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
