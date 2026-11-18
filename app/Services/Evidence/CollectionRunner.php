<?php

namespace App\Services\Evidence;

use App\Enums\EvidenceJobStatus;
use App\Models\EvidenceJob;
use App\Models\EvidenceSource;
use App\Support\Evidence\NormalisedEvidence;
use InvalidArgumentException;
use Throwable;

/**
 * Run one connector and ingest everything it returns.
 *
 * The runner owns the job lifecycle and per-record failure accounting. A
 * connector only reads; it never writes evidence itself. A failed record is
 * counted and skipped so one bad payload does not discard the rest of a batch.
 */
class CollectionRunner
{
    public function __construct(
        protected ConnectorRegistry $registry,
        protected EvidenceIngestor $ingestor,
    ) {}

    public function run(EvidenceSource $source): EvidenceJob
    {
        if (! $source->isEnabled()) {
            throw new InvalidArgumentException('The evidence source is disabled.');
        }

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Queued,
        ]);

        $job->markRunning();

        $seen = 0;
        $created = 0;
        $duplicate = 0;
        $failed = 0;
        $errors = [];

        try {
            $connector = $this->registry->for($source);

            foreach ($connector->collect($source) as $evidence) {
                if (! $evidence instanceof NormalisedEvidence) {
                    throw new InvalidArgumentException(
                        'A connector must yield only '.NormalisedEvidence::class.' instances.'
                    );
                }

                $seen++;

                try {
                    $record = $this->ingestor->ingest($source, $job, $evidence);

                    $record->wasRecentlyCreated ? $created++ : $duplicate++;
                } catch (Throwable $exception) {
                    $failed++;

                    if (count($errors) < 5) {
                        $errors[] = "{$evidence->externalId}: {$exception->getMessage()}";
                    }
                }
            }

            $job->forceFill([
                'records_seen' => $seen,
                'records_created' => $created,
                'records_duplicate' => $duplicate,
                'records_failed' => $failed,
                'error' => $errors === [] ? null : implode(PHP_EOL, $errors),
            ])->save();

            $job->markCompleted();

            $source->forceFill(['last_run_at' => now()])->save();
        } catch (Throwable $exception) {
            $job->forceFill([
                'records_seen' => $seen,
                'records_created' => $created,
                'records_duplicate' => $duplicate,
                'records_failed' => $failed,
            ])->save();

            $job->markFailed($exception->getMessage());
        }

        return $job->refresh();
    }

    /**
     * Collect every enabled source. Failures are recorded per source and do not
     * stop the rest of the run.
     *
     * @return array<int, EvidenceJob>
     */
    public function runAll(): array
    {
        $jobs = [];

        foreach (EvidenceSource::query()->where('enabled', true)->orderBy('id')->get() as $source) {
            $jobs[] = $this->run($source);
        }

        return $jobs;
    }
}
