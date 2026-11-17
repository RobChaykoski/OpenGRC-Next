<?php

namespace App\Console\Commands;

use App\Support\MigrationManifest;
use Illuminate\Console\Command;

class MigrationsManifest extends Command
{
    protected $signature = 'opengrc:migrations:manifest';

    protected $description = 'Regenerate the migration checksum manifest';

    public function handle(): int
    {
        $count = MigrationManifest::generate();

        $this->info("Wrote checksums for {$count} migration(s) to database/migration-manifest.json.");

        return self::SUCCESS;
    }
}
