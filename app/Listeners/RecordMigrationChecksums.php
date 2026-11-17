<?php

namespace App\Listeners;

use App\Support\MigrationManifest;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RecordMigrationChecksums
{
    public function handle(MigrationsEnded $event): void
    {
        try {
            if (! Schema::hasTable('migrations') || ! Schema::hasColumn('migrations', 'checksum')) {
                return;
            }

            $checksums = MigrationManifest::checksums();

            DB::table('migrations')
                ->whereNull('checksum')
                ->orderBy('id')
                ->each(function ($migration) use ($checksums): void {
                    DB::table('migrations')
                        ->where('id', $migration->id)
                        ->update([
                            'checksum' => $checksums[$migration->migration] ?? null,
                            'applied_at' => $migration->applied_at ?? now(),
                        ]);
                });
        } catch (Throwable) {
            // A migration run must not fail because the audit backfill could not
            // read the manifest; opengrc:migrations:verify reports it explicitly.
        }
    }
}
