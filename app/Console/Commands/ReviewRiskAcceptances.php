<?php

namespace App\Console\Commands;

use App\Enums\RiskAcceptanceStatus;
use App\Models\Risk;
use Illuminate\Console\Command;

/**
 * Enforce risk acceptance expiry.
 *
 * An accepted residual risk is a dated decision. When the date passes, the
 * acceptance is Expired; it does not roll forward. Re-acceptance is a new
 * decision with a named acceptor and a new end date.
 */
class ReviewRiskAcceptances extends Command
{
    protected $signature = 'opengrc:risks:acceptance
                            {--dry-run : Report expiries without changing status}';

    protected $description = 'Mark accepted risks past their acceptance expiry and report upcoming expiries';

    public function handle(): int
    {
        $expired = Risk::query()->acceptanceExpired();
        $expiredCount = (clone $expired)->count();
        $expiringCount = Risk::query()->acceptanceExpiring()->count();

        if ($expiredCount === 0 && $expiringCount === 0) {
            $this->info('No risk acceptances are expired or expiring in the next 30 days.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$expiredCount} acceptance(s) would expire; {$expiringCount} expire within 30 days.");

            return self::SUCCESS;
        }

        $updated = $expired->update([
            'acceptance_status' => RiskAcceptanceStatus::Expired,
            'updated_at' => now(),
        ]);

        $this->info("Marked {$updated} acceptance(s) expired; {$expiringCount} expire within 30 days.");

        return self::SUCCESS;
    }
}
