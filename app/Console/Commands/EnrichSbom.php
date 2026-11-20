<?php

namespace App\Console\Commands;

use App\Models\SbomDocument;
use App\Services\SupplyChain\SbomEnricher;
use Illuminate\Console\Command;
use Throwable;

class EnrichSbom extends Command
{
    protected $signature = 'opengrc:sbom:enrich
                            {sbom : SBOM document ID}
                            {--dry-run : Report what would be enriched without calling deps.dev}';

    protected $description = 'Enrich SBOM components with deps.dev licenses and OpenSSF Scorecard data';

    public function handle(SbomEnricher $enricher): int
    {
        $document = SbomDocument::query()->find((int) $this->argument('sbom'));

        if (! $document instanceof SbomDocument) {
            $this->error('No SBOM document found for that ID.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $components = $document->components()->whereNotNull('purl')->whereNotNull('version')->count();
            $this->info("{$document->reference()}: {$components} component(s) with a purl and version.");
            $this->comment('Dry run: deps.dev was not called and nothing was written.');

            return self::SUCCESS;
        }

        try {
            $counts = $enricher->enrich($document);
        } catch (Throwable $exception) {
            $this->error('Enrichment failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Result', 'Count'],
            [
                ['Components', $counts['components']],
                ['Enriched', $counts['enriched']],
                ['Skipped (unsupported)', $counts['skipped']],
                ['Scorecards', $counts['scorecards']],
                ['Failed', $counts['failed']],
            ]
        );

        foreach ($counts['errors'] as $error) {
            $this->warn($error);
        }

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
