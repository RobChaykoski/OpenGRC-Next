<?php

namespace App\Console\Commands;

use App\Services\AppLogger;
use App\Services\Evidence\EvidenceLedger;
use App\Support\InstanceMode;
use Illuminate\Console\Command;

class InstanceProduction extends Command
{
    protected $signature = 'opengrc:instance:production
                            {--reason= : Why this instance is being promoted}
                            {--force : Promote even if evidence backfill reports errors}';

    protected $description = 'Promote this instance to production and lock evidence immutability';

    public function handle(EvidenceLedger $ledger): int
    {
        if (InstanceMode::isExplicitlyProduction()) {
            $this->info('This instance is already in production mode.');

            return self::SUCCESS;
        }

        $reason = trim((string) $this->option('reason'));

        if (mb_strlen($reason) < 10) {
            $this->error('Provide --reason with at least 10 characters explaining the promotion.');

            return self::FAILURE;
        }

        $this->warn('Promotion is a one-way change: evidence can no longer be deleted or repointed at another file.');

        $backfill = $ledger->backfill();

        $this->info("Backfilled {$backfill['recorded']} evidence record(s).");

        foreach ($backfill['errors'] as $error) {
            $this->error($error);
        }

        if ($backfill['errors'] !== [] && ! $this->option('force')) {
            $this->error('Evidence backfill failed; fix the errors or pass --force after review.');

            return self::FAILURE;
        }

        InstanceMode::activateProduction(auth()->id(), $reason);

        AppLogger::info(
            category: 'instance',
            event: 'ProductionModeActivated',
            message: 'Instance promoted to production mode',
            context: ['reason' => $reason, 'backfilled' => $backfill['recorded']]
        );

        $this->info('Production mode enabled. Evidence is now append-only.');

        return self::SUCCESS;
    }
}
