<?php

namespace App\Console\Commands;

use App\Services\Evidence\EvidenceLedger;
use Illuminate\Console\Command;

class EvidenceVerify extends Command
{
    protected $signature = 'opengrc:evidence:verify
                            {--strict : Treat warnings as failures}
                            {--json : Output JSON}';

    protected $description = 'Verify evidence hashes, ledger chains, and withdrawal records';

    public function handle(EvidenceLedger $ledger): int
    {
        $result = $ledger->verifyAll();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Checked {$result['checked']} evidence record(s).");

            foreach ($result['errors'] as $error) {
                $this->error($error);
            }

            foreach ($result['warnings'] as $warning) {
                $this->warn($warning);
            }
        }

        $failed = $result['errors'] !== []
            || ($this->option('strict') && $result['warnings'] !== []);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
