<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Asset;
use App\Services\Assessment\CsetImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportCsetAssessment extends Command
{
    protected $signature = 'opengrc:cset:import
                            {path : Path to a PCII-scrubbed CSET JSON export}
                            {--application= : Application ID to link}
                            {--asset= : Asset ID to link}
                            {--dry-run : Parse and report without writing findings}';

    protected $description = 'Import a CSET JSON assessment and create findings from its observations';

    public function handle(CsetImporter $importer): int
    {
        $applicationId = $this->option('application');
        $assetId = $this->option('asset');

        $application = $applicationId === null ? null : Application::query()->find((int) $applicationId);

        if ($applicationId !== null && $application === null) {
            $this->error('No application found for that ID.');

            return self::FAILURE;
        }

        $asset = $assetId === null ? null : Asset::query()->find((int) $assetId);

        if ($assetId !== null && $asset === null) {
            $this->error('No asset found for that ID.');

            return self::FAILURE;
        }

        try {
            $summary = $importer->preview((string) $this->argument('path'));

            if ($this->option('dry-run')) {
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Assessment', $summary['name']],
                        ['Standards', $summary['standards'] === [] ? '—' : implode(', ', $summary['standards'])],
                        ['Maturity models', $summary['models'] === [] ? '—' : implode(', ', $summary['models'])],
                        ['Requirements', $summary['requirements']],
                        ['Questions', $summary['questions']],
                        ['Negative answers', $summary['negative_answers']],
                        ['Observations', $summary['observations']],
                    ]
                );
                $this->comment('Dry run: nothing was written.');

                return self::SUCCESS;
            }

            $report = $importer->import(
                (string) $this->argument('path'),
                $application?->getKey(),
                $asset?->getKey(),
            );

            $this->info("Imported {$report->reference()}: {$report->finding_count} finding(s) from CSET.");
        } catch (Throwable $exception) {
            $this->error('CSET import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
