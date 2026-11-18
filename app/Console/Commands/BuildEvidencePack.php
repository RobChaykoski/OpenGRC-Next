<?php

namespace App\Console\Commands;

use App\Services\Assurance\EvidencePackBuilder;
use Illuminate\Console\Command;
use RuntimeException;

class BuildEvidencePack extends Command
{
    protected $signature = 'opengrc:evidence:pack
                            {framework : Framework code from the canonical catalogue}
                            {--output= : Path for the ZIP file}';

    protected $description = 'Build an audit evidence pack (index, hashes, validation results, findings) for one framework';

    public function handle(EvidencePackBuilder $builder): int
    {
        $framework = (string) $this->argument('framework');
        $output = (string) ($this->option('output')
            ?: storage_path('app/exports/evidence-pack-'.$framework.'-'.now()->format('Ymd-His').'.zip'));

        try {
            $result = $builder->build($framework, $output);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Item', 'Count'],
            collect($result['counts'])->map(fn (int $count, string $item): array => [$item, $count])->values()->all()
        );

        $this->info('Evidence pack written to '.$result['path']);
        $this->line('Raw payloads and attachments are excluded by design; evidence hashes are included.');

        return self::SUCCESS;
    }
}
