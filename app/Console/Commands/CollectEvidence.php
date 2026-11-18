<?php

namespace App\Console\Commands;

use App\Enums\EvidenceJobStatus;
use App\Models\EvidenceJob;
use App\Models\EvidenceSource;
use App\Services\Evidence\CollectionRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CollectEvidence extends Command
{
    protected $signature = 'opengrc:evidence:collect
                            {--source= : Collect one evidence source ID instead of every enabled source}';

    protected $description = 'Run read-only evidence connectors and store normalised measurements';

    public function handle(CollectionRunner $runner): int
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
}
