<?php

namespace App\Console\Commands;

use App\Models\SbomDocument;
use App\Services\SupplyChain\OsvClient;
use App\Services\SupplyChain\SbomScanner;
use Illuminate\Console\Command;
use Throwable;

class ScanSbom extends Command
{
    protected $signature = 'opengrc:sbom:scan
                            {sbom : SBOM document ID}
                            {--dry-run : Report the components that would be queried without calling OSV}';

    protected $description = 'Query OSV for an imported SBOM and create or update vulnerability findings';

    public function handle(OsvClient $client, SbomScanner $scanner): int
    {
        $document = SbomDocument::query()->find((int) $this->argument('sbom'));

        if (! $document instanceof SbomDocument) {
            $this->error('No SBOM document found for that ID.');

            return self::FAILURE;
        }

        $components = $document->components()
            ->whereNotNull('purl')
            ->whereNotNull('version')
            ->get();

        if ($this->option('dry-run')) {
            $queries = $client->queries($components);
            $this->info(
                "{$document->reference()}: {$components->count()} component(s); ".count($queries).
                ' would be queried; '.($components->count() - count($queries)).' unsupported.'
            );
            $this->comment('Dry run: OSV was not called and nothing was written.');

            return self::SUCCESS;
        }

        try {
            $counts = $scanner->scan($document);
        } catch (Throwable $exception) {
            $this->error('SBOM scan failed: '.$exception->getMessage());

            return self::FAILURE;
        }

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
