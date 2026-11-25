<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Control;
use App\Services\Evidence\Prismor\PrismorAttestationImporter;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ImportPrismorAttestation extends Command
{
    private const MAX_BYTES = 32 * 1024 * 1024;

    protected $signature = 'opengrc:evidence:prismor-attestation
                            {path : Prismor attestation bundle JSON file}
                            {--source=Prismor : Evidence source name}
                            {--control= : Control code to link}
                            {--asset= : Asset name, hostname or asset tag}
                            {--pubkey= : Base64 Ed25519 public key to pin}
                            {--pubkey-file= : File containing the base64 public key}
                            {--allow-unpinned : Verify with the bundle inline key and mark the evidence low confidence}
                            {--dry-run : Verify and report without writing}
                            {--json : Emit the report as JSON}';

    protected $description = 'Verify and import a Prismor signed attestation bundle as control evidence (ADR 0060)';

    public function handle(PrismorAttestationImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $control = $this->option('control');
        $asset = $this->option('asset');
        $source = trim((string) $this->option('source'));

        try {
            $pinnedKey = $this->pinnedKey();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

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
            $this->error('The attestation path is not a readable file.');

            return self::FAILURE;
        }

        if ((int) filesize($path) > self::MAX_BYTES) {
            $this->error('The attestation file is larger than 32 MB.');

            return self::FAILURE;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            $this->error('The attestation file could not be read.');

            return self::FAILURE;
        }

        try {
            $result = $importer->import(
                $json,
                $source,
                filled($control) ? (string) $control : null,
                filled($asset) ? (string) $asset : null,
                $pinnedKey,
                (bool) $this->option('allow-unpinned'),
                (bool) $this->option('dry-run'),
            );
        } catch (Throwable $exception) {
            $this->error('Attestation import failed: '.$exception->getMessage());

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
        $this->table(
            ['Field', 'Value'],
            [
                ['Source', $result['source']],
                ['Schema', $result['schema'] ?? '—'],
                ['Device', $result['device_id'] ?? '—'],
                ['Generated', $result['generated_at'] ?? '—'],
                ['Agents', $result['agents']],
                ['Frameworks', $result['frameworks']],
                ['Imported', $result['created']],
                ['Duplicates', $result['duplicate']],
                ['Failed', $result['failed']],
                ['Pinned key', $result['pinned'] ? 'yes' : 'no'],
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
            $this->info($result['dry_run'] ? 'Verification completed; nothing was written.' : 'Prismor attestation imported.');
        }
    }

    private function pinnedKey(): ?string
    {
        $key = $this->option('pubkey');
        $file = $this->option('pubkey-file');

        if (filled($key) && filled($file)) {
            throw new RuntimeException('Use --pubkey or --pubkey-file, not both.');
        }

        if (filled($file)) {
            if (! is_file((string) $file) || ! is_readable((string) $file)) {
                throw new RuntimeException('The public key file is not readable.');
            }

            if ((int) filesize((string) $file) > 4096) {
                throw new RuntimeException('The public key file is unexpectedly large.');
            }

            $contents = file_get_contents((string) $file);

            if ($contents === false) {
                throw new RuntimeException('The public key file could not be read.');
            }

            $key = trim($contents);
        }

        return filled($key) ? trim((string) $key) : null;
    }
}
