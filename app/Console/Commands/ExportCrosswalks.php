<?php

namespace App\Console\Commands;

use App\Models\Standard;
use App\Services\Controls\CrosswalkExporter;
use Illuminate\Console\Command;

class ExportCrosswalks extends Command
{
    protected $signature = 'opengrc:crosswalk:export
                            {source : Source framework ID or code}
                            {target : Target framework ID or code}
                            {--path= : Output CSV path}
                            {--include-drafts : Include draft and rejected mappings}';

    protected $description = 'Export approved control crosswalks between two frameworks as CSV';

    public function handle(CrosswalkExporter $exporter): int
    {
        $source = $this->standard((string) $this->argument('source'));
        $target = $this->standard((string) $this->argument('target'));

        if (! $source instanceof Standard || ! $target instanceof Standard) {
            $this->error('Both frameworks must exist. Use a standard ID or code.');

            return self::FAILURE;
        }

        if ($source->is($target)) {
            $this->error('Choose two different frameworks.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: storage_path(
            "app/exports/crosswalks-{$source->code}-{$target->code}.csv"
        ));

        $count = $exporter->write($path, $source, $target, (bool) $this->option('include-drafts'));

        $this->info("Exported {$count} crosswalk(s) to {$path}.");

        return self::SUCCESS;
    }

    private function standard(string $value): ?Standard
    {
        if (is_numeric($value)) {
            $standard = Standard::query()->find((int) $value);

            if ($standard instanceof Standard) {
                return $standard;
            }
        }

        return Standard::query()->where('code', $value)->first();
    }
}
