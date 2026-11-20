<?php

namespace App\Console\Commands;

use App\Services\Secrets\OpenBaoSync;
use Illuminate\Console\Command;
use Throwable;

class SyncOpenBao extends Command
{
    protected $signature = 'opengrc:openbao:sync
                            {--dry-run : Report metadata that would be refreshed without writing}';

    protected $description = 'Refresh non-human identity rotation dates from OpenBao KV metadata';

    public function handle(OpenBaoSync $sync): int
    {
        try {
            $counts = $sync->sync((bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->error('OpenBao sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Result', 'Count'],
            [
                ['Checked', $counts['checked']],
                ['Updated', $counts['updated']],
                ['Skipped', $counts['skipped']],
                ['Failed', $counts['failed']],
            ]
        );

        foreach ($counts['errors'] as $error) {
            $this->warn($error);
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was written.');
        }

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
