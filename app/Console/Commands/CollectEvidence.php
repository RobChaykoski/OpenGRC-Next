<?php

namespace App\Console\Commands;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Models\EvidenceJob;
use App\Models\EvidenceSource;
use App\Services\Evidence\CollectionRunner;
use App\Services\Evidence\ConnectorRegistry;
use App\Support\Evidence\NormalisedEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class CollectEvidence extends Command
{
    protected $signature = 'opengrc:evidence:collect
                            {--source= : Collect one evidence source ID instead of every enabled source}
                            {--dry-run : Read the source and report what would be ingested without writing evidence}';

    protected $description = 'Run read-only evidence connectors and store normalised measurements';

    public function handle(CollectionRunner $runner, ConnectorRegistry $registry): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun($registry);
        }

        if ($this->option('source') !== null) {
            $source = EvidenceSource::query()->find($this->option('source'));

            if (! $source instanceof EvidenceSource) {
                $this->error('No evidence source found for that ID.');

                return self::FAILURE;
            }

            if (! $source->isEnabled()) {
                $this->error('That evidence source is disabled.');

                return self::FAILURE;
            }

            $jobs = [$runner->run($source)];
        } else {
            if (! EvidenceSource::query()->where('enabled', true)->exists()) {
                $this->info('No enabled evidence sources.');

                return self::SUCCESS;
            }

            $jobs = $runner->runAll();
        }

        $this->table(
            ['Source', 'Status', 'Seen', 'Created', 'Duplicate', 'Failed', 'Error'],
            array_map(fn (EvidenceJob $job): array => [
                $job->source?->name ?? '—',
                $job->status instanceof EvidenceJobStatus ? $job->status->getLabel() : '—',
                $job->records_seen,
                $job->records_created,
                $job->records_duplicate,
                $job->records_failed,
                Str::limit((string) $job->error, 60) ?: '—',
            ], $jobs)
        );

        $failed = collect($jobs)->contains(
            fn (EvidenceJob $job): bool => $job->status === EvidenceJobStatus::Failed || $job->records_failed > 0
        );

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Read the selected sources and report what they would ingest.
     *
     * This uses the same registry and connector as a real run, but never creates
     * an EvidenceJob, an EvidenceRecord or a validation. It is the safe way to
     * check a new endpoint, a rotated credential or a CA bundle before enabling
     * a source, and it mirrors the real command's source selection so a dry run
     * cannot succeed where the real run would refuse.
     */
    protected function dryRun(ConnectorRegistry $registry): int
    {
        if ($this->option('source') !== null) {
            $source = EvidenceSource::query()->find($this->option('source'));

            if (! $source instanceof EvidenceSource) {
                $this->error('No evidence source found for that ID.');

                return self::FAILURE;
            }

            if (! $source->isEnabled()) {
                $this->error('That evidence source is disabled.');

                return self::FAILURE;
            }

            $sources = collect([$source]);
        } else {
            $sources = EvidenceSource::query()->where('enabled', true)->orderBy('id')->get();
        }

        if ($sources->isEmpty()) {
            $this->info('No enabled evidence sources.');

            return self::SUCCESS;
        }

        $rows = [];
        $failed = false;

        foreach ($sources as $source) {
            $counts = [
                ControlStatus::Effective->value => 0,
                ControlStatus::Failed->value => 0,
                ControlStatus::Unknown->value => 0,
            ];
            $seen = 0;
            $error = null;

            try {
                foreach ($registry->for($source)->collect($source) as $evidence) {
                    if (! $evidence instanceof NormalisedEvidence) {
                        throw new InvalidArgumentException(
                            'A connector must yield only '.NormalisedEvidence::class.' instances.'
                        );
                    }

                    $seen++;
                    $counts[$evidence->status->value]++;
                }
            } catch (Throwable $exception) {
                $failed = true;
                $error = $exception->getMessage();
            }

            $rows[] = [
                $source->name,
                $error === null ? 'Read' : 'Failed',
                $seen,
                $counts[ControlStatus::Effective->value],
                $counts[ControlStatus::Failed->value],
                $counts[ControlStatus::Unknown->value],
                $error === null ? '—' : Str::limit($error, 60),
            ];
        }

        $this->table(
            ['Source', 'Status', 'Seen', 'Effective', 'Failed', 'Unknown', 'Error'],
            $rows
        );

        $this->comment('Dry run: nothing was written.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
