<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Control;
use App\Services\Evidence\Pipelock\PipelockReceiptImporter;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Throwable;

class ImportPipelockReceipts extends Command
{
    private const MAX_BYTES = 32 * 1024 * 1024;

    private const MAX_RECEIPTS = 10_000;

    protected $signature = 'opengrc:evidence:pipelock
                            {path : Pipelock receipt JSON, JSON array or JSONL file}
                            {--source=Pipelock : Evidence source name}
                            {--control= : Control code to link}
                            {--asset= : Asset name, hostname or asset tag}
                            {--pubkey= : 64-hex Ed25519 signer key to pin}
                            {--pubkey-file= : File containing the 64-hex signer key}
                            {--allow-unpinned : Verify with the receipt inline key and mark the evidence low confidence}
                            {--dry-run : Verify and report without writing}
                            {--json : Emit the report as JSON}';

    protected $description = 'Verify and import Pipelock signed action receipts as control evidence (ADR 0060)';

    public function handle(PipelockReceiptImporter $importer): int
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

        try {
            $receipts = $this->readReceipts($path);
        } catch (Throwable $exception) {
            $this->error('Cannot read the receipt file: '.$exception->getMessage());

            return self::FAILURE;
        }

        $result = $importer->import(
            $receipts,
            $source,
            filled($control) ? (string) $control : null,
            filled($asset) ? (string) $asset : null,
            $pinnedKey,
            (bool) $this->option('allow-unpinned'),
            (bool) $this->option('dry-run'),
        );

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
                ['Receipts', $result['receipts']],
                ['Verified', $result['verified']],
                ['Imported', $result['created']],
                ['Duplicates', $result['duplicate']],
                ['Failed', $result['failed']],
                ['Pinned key', $result['pinned'] ? 'yes' : 'no'],
                ['Verdicts', $this->counts($result['verdicts'])],
                ['Action types', $this->counts($result['action_types'])],
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
            $this->info($result['dry_run'] ? 'Verification completed; nothing was written.' : 'Pipelock receipts imported.');
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function counts(array $counts): string
    {
        if ($counts === []) {
            return '—';
        }

        $parts = [];

        foreach ($counts as $label => $count) {
            $parts[] = "{$label}: {$count}";
        }

        return implode(', ', $parts);
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

        if (! filled($key)) {
            return null;
        }

        $key = strtolower(trim((string) $key));

        if (strlen($key) !== 64 || ctype_xdigit($key) !== true) {
            throw new RuntimeException('The pinned signer key must be 64 hex characters.');
        }

        return $key;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readReceipts(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The path is not a readable file.');
        }

        if ((int) filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('The file is larger than 32 MB.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The file could not be read.');
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            return $this->normalise($decoded);
        } catch (JsonException) {
            return $this->readJsonLines($contents);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalise(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw new RuntimeException('The receipt file must contain a JSON object or array.');
        }

        if (! array_is_list($decoded)) {
            return [$decoded];
        }

        if (count($decoded) > self::MAX_RECEIPTS) {
            throw new RuntimeException('The file contains more than '.self::MAX_RECEIPTS.' receipts.');
        }

        $receipts = [];

        foreach ($decoded as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new RuntimeException('Every item in the array must be a receipt object.');
            }

            $receipts[] = $item;
        }

        return $receipts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJsonLines(string $contents): array
    {
        $receipts = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (count($receipts) >= self::MAX_RECEIPTS) {
                throw new RuntimeException('The file contains more than '.self::MAX_RECEIPTS.' receipts.');
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('A line is not valid JSON: '.$exception->getMessage(), 0, $exception);
            }

            if (! is_array($decoded) || array_is_list($decoded)) {
                throw new RuntimeException('Every line must be a receipt object.');
            }

            $receipts[] = $decoded;
        }

        if ($receipts === []) {
            throw new RuntimeException('The file contains no receipts.');
        }

        return $receipts;
    }
}
