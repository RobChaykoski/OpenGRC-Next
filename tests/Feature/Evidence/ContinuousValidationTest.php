<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSeverity;
use App\Enums\EvidenceSourceType;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Models\Asset;
use App\Models\Control;
use App\Models\ControlValidation;
use App\Models\ControlValidationRule;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\Finding;
use App\Models\User;
use App\Services\Evidence\ControlValidator;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContinuousValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_failed_validation_opens_one_finding_with_an_owner_and_due_date(): void
    {
        $owner = User::factory()->create();
        $control = $this->control('SI-2', $owner);
        [$source, $job] = $this->sourceAndJob();
        $asset = $this->asset('web-01');
        $rule = $this->rule($control, $source);

        $this->ingest($source, $job, $control, $asset, $this->failingPayload());

        $run = app(ControlValidator::class)->validateRule($rule);

        $this->assertSame(1, $run->validations);
        $this->assertSame(1, $run->failed);
        $this->assertSame(1, $run->findingsOpened);

        $finding = Finding::query()->sole();

        $this->assertSame(FindingStatus::Open, $finding->status);
        $this->assertSame(FindingSource::Automated, $finding->source);
        $this->assertSame(EvidenceSeverity::High, $finding->severity);
        $this->assertSame($control->getKey(), $finding->control_id);
        $this->assertSame($asset->getKey(), $finding->asset_id);
        $this->assertSame($owner->getKey(), $finding->owner_id);
        $this->assertSame(1, $finding->occurrences);
        $this->assertNotNull($finding->due_at);
        $this->assertSame('F-00001', $finding->reference());
        $this->assertStringContainsString('securityUpdates', $finding->description);
    }

    #[Test]
    public function a_repeat_failure_updates_the_open_finding_instead_of_creating_another(): void
    {
        $control = $this->control('SI-2');
        [$source, $job] = $this->sourceAndJob();
        $asset = $this->asset('web-01');
        $rule = $this->rule($control, $source);

        $this->ingest($source, $job, $control, $asset, $this->failingPayload(), '2026-11-17T09:00:00Z');
        app(ControlValidator::class)->validateRule($rule);

        $this->ingest($source, $job, $control, $asset, $this->failingPayload(), '2026-11-18T09:00:00Z');
        $run = app(ControlValidator::class)->validateRule($rule);

        $this->assertSame(1, Finding::query()->count());

        $finding = Finding::query()->sole();
        $this->assertSame(2, $finding->occurrences);
        $this->assertSame(1, $run->findingsUpdated);
        $this->assertSame(0, $run->findingsOpened);
    }

    #[Test]
    public function passing_evidence_resolves_the_open_finding_for_that_asset(): void
    {
        $control = $this->control('SI-2');
        [$source, $job] = $this->sourceAndJob();
        $asset = $this->asset('web-01');
        $rule = $this->rule($control, $source);

        $this->ingest($source, $job, $control, $asset, $this->failingPayload(), '2026-11-17T09:00:00Z');
        app(ControlValidator::class)->validateRule($rule);

        $this->ingest($source, $job, $control, $asset, $this->passingPayload(), '2026-11-18T09:00:00Z');
        $run = app(ControlValidator::class)->validateRule($rule);

        $validation = ControlValidation::query()->latest('id')->firstOrFail();

        $this->assertSame(ControlStatus::Effective, $validation->status);
        $this->assertSame(1, $run->effective);
        $this->assertSame(1, $run->findingsResolved);

        $finding = Finding::query()->sole();
        $this->assertSame(FindingStatus::Resolved, $finding->status);
        $this->assertNotNull($finding->resolved_at);
        $this->assertStringContainsString('passed', (string) $finding->resolution_notes);
    }

    #[Test]
    public function no_evidence_is_unknown_and_opens_no_finding(): void
    {
        $control = $this->control('SI-2');
        [$source] = $this->sourceAndJob();
        $rule = $this->rule($control, $source);

        $run = app(ControlValidator::class)->validateRule($rule);

        $validation = ControlValidation::query()->sole();

        $this->assertSame(1, $run->unknown);
        $this->assertSame(ControlStatus::Unknown, $validation->status);
        $this->assertNull($validation->asset_id);
        $this->assertSame('no evidence', $validation->result['reason']);
        $this->assertSame(0, Finding::query()->count());
    }

    #[Test]
    public function a_fleet_rule_reports_each_asset_separately(): void
    {
        $control = $this->control('SI-2');
        [$source, $job] = $this->sourceAndJob();
        $failing = $this->asset('web-01');
        $passing = $this->asset('web-02');
        $rule = $this->rule($control, $source);

        $this->ingest($source, $job, $control, $failing, $this->failingPayload());
        $this->ingest($source, $job, $control, $passing, $this->passingPayload());

        $run = app(ControlValidator::class)->validateRule($rule);

        $this->assertSame(2, $run->validations);
        $this->assertSame(1, $run->failed);
        $this->assertSame(1, $run->effective);
        $this->assertSame(1, $run->findingsOpened);
        $this->assertSame($failing->getKey(), Finding::query()->sole()->asset_id);
    }

    #[Test]
    public function a_rule_with_an_evidence_type_maps_across_controls(): void
    {
        $primary = $this->control('SI-2');
        $other = $this->control('A.8.8');
        [$source, $job] = $this->sourceAndJob();
        $asset = $this->asset('web-01');
        $rule = $this->rule($primary, $source);

        // The evidence is directly linked to A.8.8; the SI-2 rule still claims it
        // because the rule names the evidence type. This is the crosswalk.
        $this->ingest($source, $job, $other, $asset, $this->failingPayload());

        $run = app(ControlValidator::class)->validateRule($rule);

        $this->assertSame(1, $run->failed);
        $this->assertSame($primary->getKey(), ControlValidation::query()->sole()->control_id);
    }

    #[Test]
    public function unmapped_hosts_are_validated_separately_by_external_id(): void
    {
        $control = $this->control('SI-2');
        [$source, $job] = $this->sourceAndJob();
        $rule = $this->rule($control, $source);

        // No Asset rows exist. Both records have asset_id null, but they are
        // different hosts and must not collapse into one fleet validation.
        $this->ingestUnmapped($source, $job, $control, 'patchmon:web-01', $this->failingPayload());
        $this->ingestUnmapped($source, $job, $control, 'patchmon:web-02', $this->passingPayload());

        $run = app(ControlValidator::class)->validateRule($rule);

        $this->assertSame(2, $run->validations);
        $this->assertSame(1, $run->failed);
        $this->assertSame(1, $run->effective);
        $this->assertSame(1, $run->findingsOpened);
        $this->assertSame(1, Finding::query()->count());
        $this->assertSame(
            'patchmon:web-01',
            ControlValidation::query()->where('status', ControlStatus::Failed)->sole()->asset_identifier
        );
    }

    #[Test]
    public function a_status_change_is_recorded_as_drift(): void
    {
        $control = $this->control('SI-2');
        [$source, $job] = $this->sourceAndJob();
        $asset = $this->asset('web-01');
        $rule = $this->rule($control, $source);

        $this->ingest($source, $job, $control, $asset, $this->passingPayload(), '2026-11-17T09:00:00Z');
        app(ControlValidator::class)->validateRule($rule);

        $this->ingest($source, $job, $control, $asset, $this->failingPayload(), '2026-11-18T09:00:00Z');
        app(ControlValidator::class)->validateRule($rule);

        $latest = ControlValidation::query()->latest('id')->firstOrFail();
        $first = ControlValidation::query()->oldest('id')->firstOrFail();

        $this->assertSame(ControlStatus::Failed, $latest->status);
        $this->assertTrue($latest->result['drift']);
        $this->assertSame('effective', $latest->result['previous_status']);
        $this->assertFalse($first->result['drift']);
    }

    #[Test]
    public function a_rule_only_evaluates_the_evidence_type_it_names(): void
    {
        $control = $this->control('SI-2');
        [$source, $job] = $this->sourceAndJob();
        $rule = $this->rule($control, $source);

        $this->ingest(
            $source,
            $job,
            $control,
            null,
            ['securityUpdates' => 12],
            '2026-11-17T09:00:00Z',
            'identity-posture'
        );

        app(ControlValidator::class)->validateRule($rule);

        $this->assertSame(ControlStatus::Unknown, ControlValidation::query()->sole()->status);
        $this->assertSame(0, Finding::query()->count());
    }

    #[Test]
    public function a_disabled_rule_is_not_evaluated(): void
    {
        $control = $this->control('SI-2');
        [$source] = $this->sourceAndJob();
        $rule = $this->rule($control, $source);
        $rule->forceFill(['enabled' => false])->save();

        $run = app(ControlValidator::class)->validateRule($rule);

        $this->assertSame(0, $run->validations);
        $this->assertSame(0, ControlValidation::query()->count());
    }

    #[Test]
    public function the_validation_command_writes_results_and_exits_successfully(): void
    {
        $control = $this->control('SI-2');
        [$source, $job] = $this->sourceAndJob();
        $asset = $this->asset('web-01');
        $this->rule($control, $source);
        $this->ingest($source, $job, $control, $asset, $this->failingPayload());

        $this->artisan('opengrc:evidence:validate')
            ->expectsOutputToContain('Validations written')
            ->assertSuccessful();

        $this->assertSame(1, ControlValidation::query()->count());
        $this->assertSame(1, Finding::query()->count());
    }

    protected function control(string $code, ?User $owner = null): Control
    {
        return Control::factory()->create([
            'code' => $code,
            'control_owner_id' => $owner?->getKey(),
        ]);
    }

    /**
     * @return array{0: EvidenceSource, 1: EvidenceJob}
     */
    protected function sourceAndJob(): array
    {
        $source = EvidenceSource::create([
            'name' => 'PatchMon',
            'type' => EvidenceSourceType::Patchmon,
            'enabled' => true,
            'configuration' => ['base_url' => 'https://patchmon.test'],
        ]);

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Queued,
        ]);

        return [$source, $job];
    }

    protected function asset(string $name): Asset
    {
        return Asset::create([
            'asset_tag' => 'AT-'.Str::upper(Str::random(8)),
            'name' => $name,
        ]);
    }

    protected function rule(Control $control, EvidenceSource $source): ControlValidationRule
    {
        return ControlValidationRule::create([
            'control_id' => $control->getKey(),
            'source_id' => $source->getKey(),
            'definition' => [
                'match' => ['evidence_type' => 'patch-compliance'],
                'assert' => [
                    ['path' => 'securityUpdates', 'op' => 'eq', 'value' => 0],
                    ['path' => 'criticalUpdates', 'op' => 'eq', 'value' => 0],
                ],
            ],
            'severity' => EvidenceSeverity::High,
            'enabled' => true,
            'version' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ingest(
        EvidenceSource $source,
        EvidenceJob $job,
        Control $control,
        ?Asset $asset,
        array $payload,
        string $collectedAt = '2026-11-17T09:00:00Z',
        string $evidenceType = 'patch-compliance',
    ): EvidenceRecord {
        return app(EvidenceIngestor::class)->ingest($source, $job, new NormalisedEvidence(
            externalId: $asset?->name ?? 'fleet-summary',
            evidenceType: $evidenceType,
            status: ControlStatus::Unknown,
            collectedAt: CarbonImmutable::parse($collectedAt),
            payload: $payload,
            assetIdentifier: $asset?->name,
            controlIdentifier: $control->code,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ingestUnmapped(
        EvidenceSource $source,
        EvidenceJob $job,
        Control $control,
        string $externalId,
        array $payload,
        string $collectedAt = '2026-11-17T09:00:00Z',
    ): EvidenceRecord {
        return app(EvidenceIngestor::class)->ingest($source, $job, new NormalisedEvidence(
            externalId: $externalId,
            evidenceType: 'patch-compliance',
            status: ControlStatus::Unknown,
            collectedAt: CarbonImmutable::parse($collectedAt),
            payload: $payload,
            assetIdentifier: null,
            controlIdentifier: $control->code,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function failingPayload(): array
    {
        return ['securityUpdates' => 12, 'criticalUpdates' => 3];
    }

    /**
     * @return array<string, mixed>
     */
    protected function passingPayload(): array
    {
        return ['securityUpdates' => 0, 'criticalUpdates' => 0];
    }
}
