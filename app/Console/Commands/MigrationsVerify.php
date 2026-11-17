<?php

namespace App\Console\Commands;

use App\Support\MigrationManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationsVerify extends Command
{
    protected $signature = 'opengrc:migrations:verify
                            {--strict : Treat pending migrations as failures}
                            {--files-only : Check files against the manifest without a database}
                            {--record : Fill missing checksums for applied migrations}';

    protected $description = 'Verify migration checksums, applied history, and pending changes';

    public function handle(): int
    {
        $errors = MigrationManifest::verifyFiles();
        $warnings = [];

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Migration files match the checksum manifest.');

        if ($this->option('files-only')) {
            return self::SUCCESS;
        }

        if (! Schema::hasTable('migrations')) {
            $this->error('The migrations table is missing; run php artisan migrate.');

            return self::FAILURE;
        }

        $checksums = MigrationManifest::checksums();
        $applied = DB::table('migrations')->orderBy('id')->get();
        $appliedNames = $applied->pluck('migration')->all();
        $hasChecksum = Schema::hasColumn('migrations', 'checksum');

        foreach ($applied as $migration) {
            if (! isset($checksums[$migration->migration])) {
                $errors[] = "Database records migration {$migration->migration}, which is not in the manifest.";

                continue;
            }

            if (! $hasChecksum) {
                continue;
            }

            if (blank($migration->checksum)) {
                if ($this->option('record')) {
                    DB::table('migrations')->where('id', $migration->id)->update([
                        'checksum' => $checksums[$migration->migration],
                        'applied_at' => $migration->applied_at ?? now(),
                    ]);

                    continue;
                }

                $warnings[] = "Migration {$migration->migration} has no recorded checksum; run with --record.";

                continue;
            }

            if (! hash_equals((string) $migration->checksum, $checksums[$migration->migration])) {
                $errors[] = "Applied migration {$migration->migration} does not match the current file checksum.";
            }
        }

        $pending = array_values(array_diff(array_keys($checksums), $appliedNames));

        foreach ($pending as $name) {
            $warnings[] = "Migration {$name} is pending.";
        }

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        if ($errors !== []) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $pending !== []) {
            return self::FAILURE;
        }

        $this->info(count($applied).' applied, '.count($pending).' pending, checksums verified.');

        return self::SUCCESS;
    }
}
