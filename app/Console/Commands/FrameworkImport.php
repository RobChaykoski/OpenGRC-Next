<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

class FrameworkImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opengrc:framework-import
                            {code? : Framework code from config/framework_catalog.php}
                            {--list : List the frameworks that can be imported}
                            {--all : Import every framework in the catalog}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import a framework control library by catalog code';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $catalog = config('framework_catalog.standards', []);

        if ($catalog === []) {
            $this->error('The framework catalog is empty.');

            return Command::FAILURE;
        }

        if ($this->option('list') || ($this->argument('code') === null && ! $this->option('all'))) {
            $this->listFrameworks($catalog);

            return Command::SUCCESS;
        }

        if ($this->option('all')) {
            return $this->importAll($catalog);
        }

        $code = (string) $this->argument('code');

        if (! isset($catalog[$code])) {
            $this->error("Unknown framework [{$code}]. Run with --list to see the catalog.");

            return Command::FAILURE;
        }

        return $this->importOne($code, $catalog[$code]);
    }

    /**
     * Print the catalog.
     *
     * @param  array<string, array<string, mixed>>  $catalog
     */
    protected function listFrameworks(array $catalog): void
    {
        $this->table(
            ['Code', 'Framework', 'Licence', 'Controls'],
            array_map(fn (string $code, array $definition) => [
                $code,
                $definition['name'] ?? $code,
                $definition['licence'] ?? '-',
                $definition['expected_controls'] ?? '-',
            ], array_keys($catalog), array_values($catalog))
        );

        $this->line('Import one with: php artisan opengrc:framework-import <code>');
        $this->line('Validate after:  php artisan opengrc:framework-audit --strict');
    }

    /**
     * Import every framework in the catalog.
     *
     * @param  array<string, array<string, mixed>>  $catalog
     */
    protected function importAll(array $catalog): int
    {
        $results = [];

        foreach ($catalog as $code => $definition) {
            $results[$code] = $this->runSeeder($code, $definition);
        }

        $failures = array_keys(array_filter($results, fn (bool $ok) => ! $ok));

        if ($failures !== []) {
            $this->error('Failed to import: '.implode(', ', $failures));

            return Command::FAILURE;
        }

        $this->info('Imported '.count($results).' framework(s). Run opengrc:framework-audit to validate.');

        return Command::SUCCESS;
    }

    /**
     * Import a single framework.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function importOne(string $code, array $definition): int
    {
        if (! $this->runSeeder($code, $definition)) {
            return Command::FAILURE;
        }

        $this->info("Imported [{$code}].");
        $this->line('Validate with: php artisan opengrc:framework-audit --strict');

        return Command::SUCCESS;
    }

    /**
     * Run the importer that produces a framework.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function runSeeder(string $code, array $definition): bool
    {
        $importer = $definition['importer'] ?? null;

        if (is_string($importer) && class_exists($importer)) {
            try {
                $count = app($importer)->import($code, $definition);
                $this->line('  '.$count.' control(s) present.');

                return $count > 0;
            } catch (Throwable $e) {
                $this->error("Importer {$importer} failed: ".$e->getMessage());

                return false;
            }
        }

        $seeder = $definition['seeder'] ?? null;

        if (! is_string($seeder) || $seeder === '') {
            $this->error('No seeder is registered for this framework.');

            return false;
        }

        try {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        } catch (Throwable $e) {
            $this->error("Seeder {$seeder} failed: ".$e->getMessage());

            return false;
        }

        return true;
    }
}
