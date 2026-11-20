<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Asset;
use App\Services\Assessment\SarifImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportAssessment extends Command
{
    protected $signature = 'opengrc:assessment:import
                            {path : Path to a SARIF 2.1 report}
                            {--application= : Application ID to link}
                            {--asset= : Asset ID to link}
                            {--dry-run : Parse and report without writing findings}';

    protected $description = 'Import a SARIF report and create or update findings';

    public function handle(SarifImporter $importer): int
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
            if ($this->option('dry-run')) {
                $preview = $importer->preview((string) $this->argument('path'));

                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Name', $preview['name']],
                        ['Tool', $preview['tool']],
                        ['Version', $preview['tool_version'] ?? '—'],
                        ['Results', $preview['finding_count']],
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

            $this->info("Imported {$report->reference()}: {$report->finding_count} finding(s) from {$report->tool}.");
        } catch (Throwable $exception) {
            $this->error('Assessment import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
