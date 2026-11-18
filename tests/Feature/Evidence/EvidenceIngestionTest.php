<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\Asset;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceIngestionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_a_normalised_measurement_with_a_stable_hash(): void
    {
        [$source, $job] = $this->sourceAndJob();

        $record = app(EvidenceIngestor::class)->ingest($source, $job, $this->evidence([
            'securityUpdates' => 12,
            'criticalUpdates' => 3,
        ]));

        $this->assertSame('host-1', $record->external_id);
        $this->assertSame(ControlStatus::Failed, $record->status);
        $this->assertNotNull($record->ingested_at);
        $this->assertSame(64, mb_strlen($record->payload_sha256));

        $canonical = json_encode(
            ['criticalUpdates' => 3, 'securityUpdates' => 12],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $this->assertSame(hash('sha256', $canonical), $record->payload_sha256);
    }

    #[Test]
    public function a_replayed_measurement_is_not_duplicated(): void
    {
        [$source, $job] = $this->sourceAndJob();
        $ingestor = app(EvidenceIngestor::class);

        $first = $ingestor->ingest($source, $job, $this->evidence());
        $second = $ingestor->ingest($source, $job, $this->evidence());

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    #[Test]
    public function evidence_records_are_append_only_in_the_model(): void
    {
        [$source, $job] = $this->sourceAndJob();
        $record = app(EvidenceIngestor::class)->ingest($source, $job, $this->evidence());

        $this->expectException(LogicException::class);

        $record->update(['status' => ControlStatus::Effective]);
    }

    #[Test]
    public function evidence_records_cannot_be_deleted(): void
    {
        [$source, $job] = $this->sourceAndJob();
        $record = app(EvidenceIngestor::class)->ingest($source, $job, $this->evidence());

        $this->expectException(LogicException::class);

        $record->delete();
    }

    #[Test]
    public function the_database_trigger_blocks_a_direct_update(): void
    {
        [$source, $job] = $this->sourceAndJob();
        $record = app(EvidenceIngestor::class)->ingest($source, $job, $this->evidence());

        $this->expectException(QueryException::class);

        DB::table('evidence_records')
            ->where('id', $record->getKey())
            ->update(['status' => ControlStatus::Effective->value]);
    }

    #[Test]
    public function source_configuration_is_encrypted_at_rest(): void
    {
        [$source] = $this->sourceAndJob();

        $raw = DB::table('evidence_sources')->where('id', $source->getKey())->value('configuration');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('secret-token', $raw);
        $this->assertSame('secret-token', $source->fresh()?->configuration['token']);
    }

    #[Test]
    public function a_job_must_belong_to_the_source(): void
    {
        [$source] = $this->sourceAndJob();

        $other = EvidenceSource::create([
            'name' => 'Other source',
            'type' => EvidenceSourceType::Other,
            'enabled' => true,
        ]);
        $job = EvidenceJob::create([
            'source_id' => $other->getKey(),
            'status' => EvidenceJobStatus::Queued,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(EvidenceIngestor::class)->ingest($source, $job, $this->evidence());
    }

    #[Test]
    public function it_maps_a_control_code_to_the_control(): void
    {
        [$source, $job] = $this->sourceAndJob();
        $control = Control::factory()->create(['code' => 'SI-2']);

        $record = app(EvidenceIngestor::class)->ingest(
            $source,
            $job,
            $this->evidence(controlIdentifier: 'SI-2'),
        );

        $this->assertSame($control->getKey(), $record->control_id);
    }

    #[Test]
    public function it_maps_an_asset_name_to_the_asset(): void
    {
        [$source, $job] = $this->sourceAndJob();
        $asset = Asset::create([
            'asset_tag' => 'AT-1',
            'name' => 'web-01',
        ]);

        $record = app(EvidenceIngestor::class)->ingest(
            $source,
            $job,
            $this->evidence(assetIdentifier: 'web-01'),
        );

        $this->assertSame($asset->getKey(), $record->asset_id);
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
            'configuration' => [
                'base_url' => 'https://patchmon.test',
                'token' => 'secret-token',
            ],
        ]);

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Queued,
        ]);

        return [$source, $job];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function evidence(
        array $payload = ['securityUpdates' => 0],
        ?string $controlIdentifier = 'SI-2',
        ?string $assetIdentifier = 'not-a-real-asset',
    ): NormalisedEvidence {
        return new NormalisedEvidence(
            externalId: 'host-1',
            evidenceType: 'patch-compliance',
            status: ControlStatus::Failed,
            collectedAt: CarbonImmutable::parse('2026-11-17T09:00:00Z'),
            payload: $payload,
            assetIdentifier: $assetIdentifier,
            controlIdentifier: $controlIdentifier,
        );
    }
}
