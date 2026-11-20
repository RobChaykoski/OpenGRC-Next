<?php

namespace App\Console\Commands;

use App\Enums\AttestationStatus;
use App\Models\AttestationReport;
use Illuminate\Console\Command;

/**
 * Enforce explicit report expiry.
 *
 * Only reports with an expires_at are touched. A SOC 2 report with a period end
 * but no expiry stays current until it is superseded; its period_end and
 * next_review_at still make the age visible in the register.
 */
class ExpireAttestationReports extends Command
{
    protected $signature = 'opengrc:attestations:expire
                            {--dry-run : Report what would expire without changing status}';

    protected $description = 'Mark current assurance reports past their expiry date as expired';

    public function handle(): int
    {
        $query = AttestationReport::query()
            ->where('status', AttestationStatus::Current)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', today());

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No assurance reports to expire.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$count} report(s) would be marked expired.");

            return self::SUCCESS;
        }

        $updated = $query->update([
            'status' => AttestationStatus::Expired,
            'updated_at' => now(),
        ]);

        $this->info("Marked {$updated} report(s) as expired.");

        return self::SUCCESS;
    }
}
