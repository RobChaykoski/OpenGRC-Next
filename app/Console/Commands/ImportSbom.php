<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Asset;
use App\Services\SupplyChain\CycloneDxImporter;
use App\Services\SupplyChain\SbomScanner;
use Illuminate\Console\Command;
use Throwable;

class ImportSbom extends Command
{
    protected $signature = 'opengrc:sbom:import
                            {path : Path to a CycloneDX JSON SBOM}
                            {--application= : Application ID to link}
                            {--asset= : Asset ID to link}
                            {--scan : Query OSV after import}
                            {--dry-run : Parse and report without writing or scanning}';

    protected $description = 'Import a CycloneDX JSON SBOM and optionally query OSV for vulnerabilities';

    public function handle(CycloneDxImporter $importer, SbomScanner $scanner): int
    {
        $path = (string) $this->argument('path');

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
                $preview = $importer->preview($path);

                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Name', $preview['name']],
                        ['Format', $preview['format']],
                        ['Spec version', $preview['spec_version'] ?? '—'],
                        ['Components', $preview['component_count']],
                    ]
                );
                $this->comment('Dry run: nothing was written and OSV was not queried.');

                return self::SUCCESS;
            }

            $document = $importer->import(
                $path,
                $application?->getKey(),
                $asset?->getKey(),
                null,
            );

            $this->info("Imported {$document->reference()}: {$document->component_count} component(s).");

            if (! $this->option('scan')) {
                return self::SUCCESS;
            }

            $counts = $scanner->scan($document);

            return $this->reportScan($counts);
        } catch (Throwable $exception) {
            $this->error('SBOM import failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $counts
     */
    protected function reportScan(array $counts): int
    {
        $this->table(
            ['Result', 'Count'],
            [
                ['Components', $counts['components']],
                ['Queried', $counts['queried']],
                ['Unsupported ecosystem', $counts['unsupported']],
                ['Vulnerabilities', $counts['vulnerabilities']],
                ['Findings created', $counts['findings_created']],
                ['Findings updated', $counts['findings_updated']],
            ]
        );

        foreach ($counts['errors'] as $error) {
            $this->warn($error);
        }

        return $counts['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
