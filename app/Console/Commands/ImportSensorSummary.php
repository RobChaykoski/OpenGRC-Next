<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Control;
use App\Services\Evidence\Sensor\SensorSummaryImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportSensorSummary extends Command
{
    private const MAX_BYTES = 32 * 1024 * 1024;

    protected $signature = 'opengrc:evidence:sensor-summary
                            {path : Aggregate sensor summary JSON file}
                            {--source=Sensor summary : Evidence source name}
                            {--control= : Control code to link}
                            {--asset= : Asset name, hostname or asset tag}
                            {--sha256= : Expected SHA-256 of the exact file bytes}
                            {--allow-unhashed : Store the summary as low confidence without a file hash}
                            {--dry-run : Validate and report without writing}
                            {--json : Emit the report as JSON}';

    protected $description = 'Import a hash-verified Suricata, Arkime or Hubble aggregate sensor summary (ADR 0060)';

    public function handle(SensorSummaryImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $control = $this->option('control');
        $asset = $this->option('asset');
        $source = trim((string) $this->option('source'));

        if ($source === '') {
            $this->error('The source name cannot be empty.');

            return self::FAILURE;
        }

        if (filled($control) && ! Control::query()->where('code', $control)->exists()) {
            $this->error("No control found with code {$control}.");

            return self::FAILURE;
        }

        if (filled($asset) && ! Asset::query()
            ->where('name', $asset)
            ->orWhere('hostname', $asset)
            ->orWhere('asset_tag', $asset)
            ->exists()) {
            $this->error("No asset found for {$asset}.");

            return self::FAILURE;
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The summary path is not a readable file.');

            return self::FAILURE;
        }

        if ((int) filesize($path) > self::MAX_BYTES) {
            $this->error('The summary file is larger than 32 MB.');

            return self::FAILURE;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            $this->error('The summary file could not be read.');

            return self::FAILURE;
        }

        try {
            $result = $importer->import(
                $json,
                $source,
                filled($control) ? (string) $control : null,
                filled($asset) ? (string) $asset : null,
                filled($this->option('sha256')) ? (string) $this->option('sha256') : null,
                (bool) $this->option('allow-unhashed'),
                (bool) $this->option('dry-run'),
            );
        } catch (Throwable $exception) {
            $this->error('Sensor summary import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->report($result);
        }

        return $result['failed'] > 0 || $result['errors'] !== []
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): void
    {
        $window = $result['window'];

        $this->table(
            ['Field', 'Value'],
            [
                ['Source', $result['source']],
                ['Sensor', $result['sensor'] ?? '—'],
                ['Window', $window['from'].' to '.$window['to']],
                ['Events', $result['events']],
                ['Imported', $result['created']],
                ['Duplicates', $result['duplicate']],
                ['Failed', $result['failed']],
                ['Hash verified', $result['hash_verified'] ? 'yes' : 'no'],
                ['Dry run', $result['dry_run'] ? 'yes' : 'no'],
            ]
        );

        foreach (array_slice($result['warnings'], 0, 10) as $warning) {
            $this->warn($warning);
        }

        foreach (array_slice($result['errors'], 0, 10) as $error) {
            $this->error($error);
        }

        if ($result['errors'] === [] && $result['failed'] === 0) {
            $this->info($result['dry_run'] ? 'Validation completed; nothing was written.' : 'Sensor summary imported.');
        }
    }
}
