<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditLedger;
use Illuminate\Console\Command;

/**
 * Verify the audit ledger chain (ADR 0056).
 *
 * A broken chain means an entry was edited, reordered or removed. Altered
 * activity rows are reported separately: the ledger is intact but the detail it
 * mirrors no longer matches. Pruned activity rows are expected and reported as
 * information, not a failure.
 */
class VerifyAuditLedger extends Command
{
    protected $signature = 'opengrc:audit:verify';

    protected $description = 'Verify the audit ledger hash chain and the activity rows it mirrors';

    public function handle(AuditLedger $ledger): int
    {
        $report = $ledger->verify();

        if ($report['total'] === 0) {
            $this->info('No audit ledger entries yet.');

            return self::SUCCESS;
        }

        if ($report['broken'] !== null) {
            $this->error("Audit ledger chain is broken at entry #{$report['broken']}.");

            return self::FAILURE;
        }

        if ($report['altered'] > 0) {
            $this->error("{$report['altered']} activity row(s) no longer match the audit ledger.");

            return self::FAILURE;
        }

        $message = "Audit ledger intact across {$report['total']} entries.";

        if ($report['missing'] > 0) {
            $message .= " {$report['missing']} mirrored activity row(s) were pruned; the ledger entries remain.";
        }

        $this->info($message);

        return self::SUCCESS;
    }
}
