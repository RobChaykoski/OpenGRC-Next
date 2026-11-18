<?php

namespace Tests\Feature\Scoping;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\Control;
use App\Models\ControlValidation;
use App\Models\EvidenceJob;
use App\Models\EvidenceSource;
use App\Services\Assurance\FrameworkGapReport;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FrameworkGapReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_splits_gaps_by_reason(): void
    {
        $unreviewed = $this->outcome('CTRL-TRAIN-001');
        $noEvidence = $this->outcome('CTRL-LOG-001');
        $stale = $this->outcome('CTRL-VULN-001', 'weekly');
        $failing = $this->outcome('CTRL-ACCESS-001');

        CanonicalFramework::create([
            'code' => 'test_framework',
            'name' => 'Test framework',
            'priority' => 1,
        ])->controls()->attach(collect([$unreviewed, $noEvidence, $stale, $failing])->map->getKey()->all());

        Control::factory()->create(['code' => 'N-1'])->canonicalOutcomes()->attach($noEvidence->getKey());

        $staleControl = Control::factory()->create(['code' => 'S-1']);
        $staleControl->canonicalOutcomes()->attach($stale->getKey());
        $this->evidenceFor($staleControl, 'S-1', 'stale-host', CarbonImmutable::now()->subDays(30));

        $failingControl = Control::factory()->create(['code' => 'F-1']);
        $failingControl->canonicalOutcomes()->attach($failing->getKey());
        ControlValidation::create([
            'control_id' => $failingControl->getKey(),
            'status' => ControlStatus::Failed,
            'evaluated_at' => now(),
        ]);

        $report = app(FrameworkGapReport::class)->forFramework('test_framework');

        $this->assertSame(4, $report['outcomes']);
        $this->assertSame(['CTRL-TRAIN-001'], array_column($report['unreviewed'], 'code'));
        $this->assertSame(['CTRL-LOG-001'], array_column($report['no_evidence'], 'code'));
        $this->assertSame(['CTRL-VULN-001'], array_column($report['stale_evidence'], 'code'));
        $this->assertSame(['CTRL-ACCESS-001'], array_column($report['failing_validation'], 'code'));
    }

    #[Test]
    public function the_condensed_report_lists_each_outcome_once_with_all_reasons(): void
    {
        $staleAndFailing = $this->outcome('CTRL-VULN-001', 'weekly');

        CanonicalFramework::create([
            'code' => 'test_framework',
            'name' => 'Test framework',
            'priority' => 1,
        ])->controls()->attach($staleAndFailing->getKey());

        $control = Control::factory()->create(['code' => 'S-1']);
        $control->canonicalOutcomes()->attach($staleAndFailing->getKey());
        $this->evidenceFor($control, 'S-1', 'stale-host', CarbonImmutable::now()->subDays(30));
        ControlValidation::create([
            'control_id' => $control->getKey(),
            'status' => ControlStatus::Failed,
            'evaluated_at' => now()->subDays(30),
        ]);

        $rows = app(FrameworkGapReport::class)->condensed('test_framework');

        $this->assertCount(1, $rows);
        $this->assertSame('CTRL-VULN-001', $rows[0]['code']);
        $this->assertEqualsCanonicalizing(['stale_evidence', 'failing_validation'], $rows[0]['reasons']);
    }

    private function outcome(string $code, ?string $frequency = null): CanonicalControl
    {
        return CanonicalControl::create([
            'code' => $code,
            'name' => $code,
            'priority' => 1,
            'frequency' => $frequency,
        ]);
    }

    private function evidenceFor(
        Control $control,
        string $controlIdentifier,
        string $externalId,
        CarbonImmutable $collectedAt,
    ): void {
        $source = EvidenceSource::create([
            'name' => 'Test source '.$externalId,
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
                externalId: $externalId,
                evidenceType: 'test',
                status: ControlStatus::Effective,
                collectedAt: $collectedAt,
                payload: ['ok' => true],
                controlIdentifier: $controlIdentifier,
            ),
        );
    }
}
