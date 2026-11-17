<?php

namespace App\Console\Commands;

use App\Services\Evidence\EvidenceLedger;
use Illuminate\Console\Command;

class EvidenceBackfill extends Command
{
    protected $signature = 'opengrc:evidence:backfill';

    protected $description = 'Create ledger entries for existing evidence records';

    public function handle(EvidenceLedger $ledger): int
    {
        $result = $ledger->backfill();

        $this->info("Recorded {$result['recorded']} ledger submission(s).");

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
