<?php

namespace Tests\Feature\Evidence;

use App\Contracts\EvidenceConnector;
use App\Enums\ControlStatus;
use App\Enums\EvidenceSourceType;
use App\Jobs\CollectEvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\CollectionRunner;
use App\Services\Evidence\ConnectorRegistry;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CollectEvidenceJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_a_unique_source_job(): void
    {
        Queue::fake();

        $source = $this->source();

        CollectEvidenceJob::dispatch($source->getKey());

        Queue::assertPushed(CollectEvidenceJob::class);
    }

    #[Test]
    public function handling_the_job_collects_the_source(): void
    {
        app(ConnectorRegistry::class)->register(
            EvidenceSourceType::Patchmon,
            JobFakeConnector::class
        );

        $source = $this->source();

        (new CollectEvidenceJob($source->getKey()))->handle(app(CollectionRunner::class));

        $this->assertSame(1, EvidenceRecord::query()->count());
        $this->assertNotNull($source->fresh()?->last_run_at);
    }

    #[Test]
    public function a_disabled_source_is_skipped(): void
    {
        app(ConnectorRegistry::class)->register(
            EvidenceSourceType::Patchmon,
            JobFakeConnector::class
        );

        $source = $this->source(['enabled' => false]);

        (new CollectEvidenceJob($source->getKey()))->handle(app(CollectionRunner::class));

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    protected function source(array $overrides = []): EvidenceSource
    {
        return EvidenceSource::create(array_merge([
            'name' => 'PatchMon job test',
            'type' => EvidenceSourceType::Patchmon,
            'enabled' => true,
            'configuration' => ['base_url' => 'https://patchmon.test/api'],
        ], $overrides));
    }
}

final class JobFakeConnector implements EvidenceConnector
{
    public function type(): EvidenceSourceType
    {
        return EvidenceSourceType::Patchmon;
    }

    public function collect(EvidenceSource $source): iterable
    {
        return [new NormalisedEvidence(
            externalId: 'patchmon:host-1',
            evidenceType: 'patch-compliance',
            status: ControlStatus::Failed,
            collectedAt: CarbonImmutable::parse('2026-11-17T09:00:00Z'),
            payload: ['securityUpdates' => 1],
        )];
    }
}
