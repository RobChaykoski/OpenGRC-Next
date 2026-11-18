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
use App\Models\EvidenceSource;
use App\Services\Evidence\EvidenceIngestor;
use App\Services\Scoping\AssuranceScore;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssuranceScoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_scores_review_evidence_quality_automation_and_monitoring_with_visible_components(): void
    {
        $validated = $this->outcome('CTRL-ACCESS-001');
        $manual = $this->outcome('CTRL-ACCESS-002');
        $reviewedOnly = $this->outcome('CTRL-LOG-001');
        $unreviewed = $this->outcome('CTRL-TRAIN-001');

        $this->framework('test_framework', [$validated, $manual, $reviewedOnly, $unreviewed]);

        $validatedControl = Control::factory()->create(['code' => 'AC-2']);
        $validatedControl->canonicalOutcomes()->attach($validated->getKey());
        ControlValidationRule::create([
            'control_id' => $validatedControl->getKey(),
            'definition' => [],
            'enabled' => true,
        ]);
        ControlValidation::create([
            'control_id' => $validatedControl->getKey(),
            'status' => ControlStatus::Effective,
            'evaluated_at' => now(),
        ]);

        $manualControl = Control::factory()->create(['code' => 'AC-3']);
        $manualControl->canonicalOutcomes()->attach($manual->getKey());
        $this->evidenceFor($manualControl, 'AC-3', EvidenceSourceType::Other);

        $reviewedControl = Control::factory()->create(['code' => 'AU-2']);
        $reviewedControl->canonicalOutcomes()->attach($reviewedOnly->getKey());
        ControlValidationRule::create([
            'control_id' => $reviewedControl->getKey(),
            'definition' => [],
            'enabled' => true,
        ]);

        $result = app(AssuranceScore::class)->forFramework('test_framework');

        $this->assertNotNull($result);
        $this->assertSame(4, $result['outcomes']);
        $this->assertSame(
            ['reviewed' => 3, 'evidenced' => 2, 'automated' => 1, 'monitored' => 2],
            $result['counts']
        );

        $this->assertSame(75, $result['components']['reviewed']['value']);
        $this->assertSame(50, $result['components']['evidence']['value']);
        $this->assertSame(75, $result['components']['quality']['value']);
        $this->assertSame(25, $result['components']['automation']['value']);
        $this->assertSame(50, $result['components']['monitoring']['value']);

        $this->assertSame(25, $result['components']['reviewed']['weight']);
        $this->assertSame(30, $result['components']['evidence']['weight']);
        $this->assertSame(20, $result['components']['quality']['weight']);
        $this->assertSame(15, $result['components']['automation']['weight']);
        $this->assertSame(10, $result['components']['monitoring']['weight']);

        $this->assertSame(58, $result['score']);
    }

    #[Test]
    public function there_is_no_score_without_outcomes(): void
    {
        CanonicalFramework::create([
            'code' => 'empty_framework',
            'name' => 'Empty',
            'priority' => 1,
        ]);

        $this->assertNull(app(AssuranceScore::class)->forFramework('empty_framework'));
        $this->assertNull(app(AssuranceScore::class)->forFramework('missing_framework'));
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
     * @param  array<int, CanonicalControl>  $outcomes
     */
    private function framework(string $code, array $outcomes): CanonicalFramework
    {
        $framework = CanonicalFramework::create([
            'code' => $code,
            'name' => 'Test framework',
            'priority' => 1,
        ]);

        $framework->controls()->attach(array_map(
            fn (CanonicalControl $outcome): int => $outcome->getKey(),
            $outcomes
        ));

        return $framework;
    }

    private function evidenceFor(Control $control, string $controlIdentifier, EvidenceSourceType $type): void
    {
        $source = EvidenceSource::create([
            'name' => 'Source '.$controlIdentifier,
            'type' => $type,
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
