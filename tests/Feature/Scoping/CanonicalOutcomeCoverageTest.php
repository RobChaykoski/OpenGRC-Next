<?php

namespace Tests\Feature\Scoping;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\Control;
use App\Models\ControlValidation;
use App\Models\ControlValidationRule;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\Policy;
use App\Services\Evidence\EvidenceIngestor;
use App\Services\Scoping\CanonicalOutcomeCoverage;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CanonicalOutcomeCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_controls_policies_and_evidence_per_outcome(): void
    {
        $outcome = $this->outcome('CTRL-ACCESS-001');
        $control = Control::factory()->create(['code' => 'T-1']);
        $control->canonicalOutcomes()->attach($outcome->getKey(), ['source' => 'manual']);

        Policy::factory()->create()->canonicalOutcomes()->attach($outcome->getKey());

        $viaControl = Policy::factory()->create();
        $control->policies()->attach($viaControl->getKey());

        // Linked both directly and via the control: still one policy.
        $both = Policy::factory()->create();
        $both->canonicalOutcomes()->attach($outcome->getKey());
        $control->policies()->attach($both->getKey());

        // Soft-deleted policies do not count.
        $retired = Policy::factory()->create();
        $retired->canonicalOutcomes()->attach($outcome->getKey());
        $retired->delete();

        $this->evidenceFor($control, 'T-1', 'host-1');

        $row = $this->rowFor($outcome);

        $this->assertSame(1, $row['controls']);
        $this->assertSame(3, $row['policies']);
        $this->assertSame(1, $row['evidence']);
        $this->assertNotNull($row['latest_evidence_at']);
    }

    #[Test]
    public function a_soft_deleted_control_does_not_count_its_policies_or_evidence(): void
    {
        $outcome = $this->outcome('CTRL-LOG-001');
        $control = Control::factory()->create(['code' => 'T-1']);
        $control->canonicalOutcomes()->attach($outcome->getKey());
        $control->policies()->attach(Policy::factory()->create()->getKey());
        $this->evidenceFor($control, 'T-1', 'host-1');

        $control->delete();

        $row = $this->rowFor($outcome);

        $this->assertSame(0, $row['controls']);
        $this->assertSame(0, $row['policies']);
        $this->assertSame(0, $row['evidence']);
        $this->assertNull($row['latest_evidence_at']);
    }

    #[Test]
    public function evidence_is_counted_only_through_a_linked_control(): void
    {
        $outcome = $this->outcome('CTRL-VULN-001');
        $control = Control::factory()->create(['code' => 'T-1']);

        $this->evidenceFor($control, 'T-1', 'host-1');

        $this->assertSame(0, $this->rowFor($outcome)['evidence']);

        $control->canonicalOutcomes()->attach($outcome->getKey());

        $this->assertSame(1, $this->rowFor($outcome)['evidence']);
    }

    #[Test]
    public function summary_can_be_scoped_to_one_framework(): void
    {
        $required = $this->outcome('CTRL-ACCESS-001');
        $other = $this->outcome('CTRL-TRAIN-001');

        CanonicalFramework::create([
            'code' => 'TEST-FW',
            'name' => 'Test framework',
            'priority' => 1,
        ])->controls()->attach($required->getKey());

        $summary = app(CanonicalOutcomeCoverage::class)->summary('TEST-FW');

        $this->assertCount(1, $summary);
        $this->assertSame('CTRL-ACCESS-001', $summary[0]['code']);
        $this->assertSame(['TEST-FW'], $summary[0]['frameworks']);
        $this->assertNotContains($other->code, array_column($summary, 'code'));
    }

    #[Test]
    public function confidence_reflects_how_evidence_arrived(): void
    {
        $outcome = $this->outcome('CTRL-ACCESS-001');
        $control = Control::factory()->create(['code' => 'T-1']);
        $control->canonicalOutcomes()->attach($outcome->getKey());

        $this->assertSame('none', $this->rowFor($outcome)['confidence_basis']);
        $this->assertSame(0, $this->rowFor($outcome)['best_confidence']);

        $control->policies()->attach(Policy::factory()->create()->getKey());

        $this->assertSame('documentation', $this->rowFor($outcome)['confidence_basis']);
        $this->assertSame(20, $this->rowFor($outcome)['best_confidence']);

        $this->evidenceFor($control, 'T-1', 'host-1');

        $this->assertSame('manual', $this->rowFor($outcome)['confidence_basis']);
        $this->assertSame(50, $this->rowFor($outcome)['best_confidence']);

        $this->machineEvidenceFor($control, 'T-1', 'host-2');

        $this->assertSame('api', $this->rowFor($outcome)['confidence_basis']);
        $this->assertSame(80, $this->rowFor($outcome)['best_confidence']);
        $this->assertTrue($this->rowFor($outcome)['automated']);

        ControlValidationRule::create([
            'control_id' => $control->getKey(),
            'definition' => [],
            'enabled' => true,
        ]);

        $this->assertTrue($this->rowFor($outcome)['monitored']);

        ControlValidation::create([
            'control_id' => $control->getKey(),
            'status' => ControlStatus::Effective,
            'evaluated_at' => now(),
        ]);

        $row = $this->rowFor($outcome);

        $this->assertSame('validation', $row['confidence_basis']);
        $this->assertSame(100, $row['best_confidence']);
        $this->assertSame(1, $row['validations']);
        $this->assertNotNull($row['latest_validation_at']);
        $this->assertTrue($row['has_evidence']);
    }

    #[Test]
    public function stale_evidence_follows_the_catalogue_frequency(): void
    {
        $weekly = CanonicalControl::create([
            'code' => 'CTRL-WEEKLY',
            'name' => 'Weekly outcome',
            'priority' => 1,
            'frequency' => 'weekly',
        ]);
        $annual = CanonicalControl::create([
            'code' => 'CTRL-ANNUAL',
            'name' => 'Annual outcome',
            'priority' => 1,
            'frequency' => 'annual',
        ]);

        $weeklyControl = Control::factory()->create(['code' => 'W-1']);
        $weeklyControl->canonicalOutcomes()->attach($weekly->getKey());
        $this->evidenceFor($weeklyControl, 'W-1', 'weekly-host', EvidenceSourceType::Other, null, CarbonImmutable::now()->subDays(30));

        $annualControl = Control::factory()->create(['code' => 'A-1']);
        $annualControl->canonicalOutcomes()->attach($annual->getKey());
        $this->evidenceFor($annualControl, 'A-1', 'annual-host', EvidenceSourceType::Other, null, CarbonImmutable::now()->subDays(200));

        $weeklyRow = $this->rowFor($weekly);
        $annualRow = $this->rowFor($annual);

        $this->assertTrue($weeklyRow['stale']);
        $this->assertSame(14, $weeklyRow['expected_interval_days']);
        $this->assertFalse($annualRow['stale']);
        $this->assertSame(400, $annualRow['expected_interval_days']);
    }

    private function outcome(string $code): CanonicalControl
    {
        return CanonicalControl::create([
            'code' => $code,
            'name' => $code,
            'priority' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFor(CanonicalControl $outcome): array
    {
        $rows = app(CanonicalOutcomeCoverage::class)->summary();

        foreach ($rows as $row) {
            if ($row['code'] === $outcome->code) {
                return $row;
            }
        }

        $this->fail("No coverage row for {$outcome->code}.");
    }

    private function evidenceFor(
        Control $control,
        string $controlIdentifier,
        string $externalId,
        EvidenceSourceType $type = EvidenceSourceType::Other,
        ?string $clientId = null,
        ?CarbonImmutable $collectedAt = null,
    ): EvidenceRecord {
        $source = EvidenceSource::create([
            'name' => 'Test source '.$externalId,
            'type' => $type,
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
                externalId: $externalId,
                evidenceType: 'test',
                status: ControlStatus::Effective,
                collectedAt: $collectedAt ?? CarbonImmutable::parse('2026-11-18T09:00:00Z'),
                payload: ['ok' => true],
                controlIdentifier: $controlIdentifier,
                clientId: $clientId,
            ),
        );
    }

    private function machineEvidenceFor(Control $control, string $controlIdentifier, string $externalId): EvidenceRecord
    {
        return $this->evidenceFor($control, $controlIdentifier, $externalId, EvidenceSourceType::Patchmon);
    }
}
