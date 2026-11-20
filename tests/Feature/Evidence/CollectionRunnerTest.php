<?php

namespace Tests\Feature\Evidence;

use App\Contracts\EvidenceConnector;
use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\CollectionRunner;
use App\Services\Evidence\ConnectorRegistry;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CollectionRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeCollectingConnector::$evidence = [];
        FakeCollectingConnector::$throw = null;

        app(ConnectorRegistry::class)->register(
            EvidenceSourceType::Patchmon,
            FakeCollectingConnector::class
        );
    }

    #[Test]
    public function it_runs_a_connector_and_records_the_job_counts(): void
    {
        FakeCollectingConnector::$evidence = [
            $this->evidence('host-1'),
            $this->evidence('host-2'),
        ];

        $source = $this->source();
        $job = app(CollectionRunner::class)->run($source);

        $this->assertSame(EvidenceJobStatus::Completed, $job->status);
        $this->assertSame(2, $job->records_seen);
        $this->assertSame(2, $job->records_created);
        $this->assertSame(0, $job->records_duplicate);
        $this->assertSame(0, $job->records_failed);
        $this->assertSame(2, EvidenceRecord::query()->count());
        $this->assertNotNull($source->fresh()?->last_run_at);
    }

    #[Test]
    public function a_replay_counts_duplicates_instead_of_creating_records(): void
    {
        FakeCollectingConnector::$evidence = [$this->evidence('host-1')];

        $source = $this->source();
        $runner = app(CollectionRunner::class);

        $runner->run($source);
        $second = $runner->run($source);

        $this->assertSame(1, $second->records_seen);
        $this->assertSame(0, $second->records_created);
        $this->assertSame(1, $second->records_duplicate);
        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_connector_failure_marks_the_job_failed_and_keeps_the_error(): void
    {
        FakeCollectingConnector::$throw = 'Vendor API unavailable';

        $source = $this->source();
        $job = app(CollectionRunner::class)->run($source);

        $this->assertSame(EvidenceJobStatus::Failed, $job->status);
        $this->assertStringContainsString('Vendor API unavailable', (string) $job->error);
        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertNull($source->fresh()?->last_run_at);
    }

    #[Test]
    public function a_disabled_source_cannot_be_collected(): void
    {
        $source = $this->source(['enabled' => false]);

        $this->expectException(InvalidArgumentException::class);

        app(CollectionRunner::class)->run($source);
    }

    #[Test]
    public function the_collect_command_runs_one_source_and_exits_successfully(): void
    {
        FakeCollectingConnector::$evidence = [$this->evidence('host-1')];

        $source = $this->source();

        $this->artisan('opengrc:evidence:collect', ['--source' => $source->getKey()])
            ->expectsOutputToContain('Created')
            ->assertSuccessful();

        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    #[Test]
    public function the_collect_command_fails_for_an_unknown_source(): void
    {
        $this->artisan('opengrc:evidence:collect', ['--source' => 999999])
            ->assertFailed();
    }

    #[Test]
    public function the_collect_command_can_dry_run_without_writing_evidence(): void
    {
        FakeCollectingConnector::$evidence = [$this->evidence('host-1')];

        $source = $this->source();

        $this->artisan('opengrc:evidence:collect', [
            '--source' => $source->getKey(),
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertDatabaseCount('evidence_jobs', 0);
        $this->assertDatabaseCount('evidence_records', 0);
    }

    #[Test]
    public function the_collect_command_dry_run_fails_when_the_connector_fails(): void
    {
        FakeCollectingConnector::$throw = 'Vendor API unavailable';

        $source = $this->source();

        $this->artisan('opengrc:evidence:collect', [
            '--source' => $source->getKey(),
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Vendor API unavailable')
            ->assertFailed();

        $this->assertDatabaseCount('evidence_jobs', 0);
    }

    #[Test]
    public function the_dry_run_reports_measurements_without_writing_anything(): void
    {
        FakeCollectingConnector::$evidence = [
            $this->evidence('host-1'),
            $this->evidence('host-2'),
        ];

        $source = $this->source();

        $this->artisan('opengrc:evidence:collect', [
            '--source' => $source->getKey(),
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertSuccessful();

        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertSame(0, EvidenceJob::query()->count());
        $this->assertNull($source->fresh()?->last_run_at);
    }

    #[Test]
    public function the_dry_run_fails_when_the_connector_throws(): void
    {
        FakeCollectingConnector::$throw = 'Vendor API unavailable';

        $source = $this->source();

        $this->artisan('opengrc:evidence:collect', [
            '--source' => $source->getKey(),
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Vendor API unavailable')
            ->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
        $this->assertSame(0, EvidenceJob::query()->count());
    }

    #[Test]
    public function the_dry_run_refuses_a_disabled_source(): void
    {
        $source = $this->source(['enabled' => false]);

        $this->artisan('opengrc:evidence:collect', [
            '--source' => $source->getKey(),
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('disabled')
            ->assertFailed();

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    protected function source(array $overrides = []): EvidenceSource
    {
        return EvidenceSource::create(array_merge([
            'name' => 'PatchMon test',
            'type' => EvidenceSourceType::Patchmon,
            'enabled' => true,
            'configuration' => ['base_url' => 'https://patchmon.test'],
        ], $overrides));
    }

    protected function evidence(string $externalId): NormalisedEvidence
    {
        return new NormalisedEvidence(
            externalId: $externalId,
            evidenceType: 'patch-compliance',
            status: ControlStatus::Failed,
            collectedAt: CarbonImmutable::parse('2026-11-17T09:00:00Z'),
            payload: ['securityUpdates' => 1],
        );
    }
}

final class FakeCollectingConnector implements EvidenceConnector
{
    /** @var array<int, NormalisedEvidence> */
    public static array $evidence = [];

    public static ?string $throw = null;

    public function type(): EvidenceSourceType
    {
        return EvidenceSourceType::Patchmon;
    }

    public function collect(EvidenceSource $source): iterable
    {
        if (self::$throw !== null) {
            throw new RuntimeException(self::$throw);
        }

        return self::$evidence;
    }
}
