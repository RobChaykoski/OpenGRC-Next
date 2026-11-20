<?php

namespace App\Console\Commands;

use App\Enums\PolicyExceptionStatus;
use App\Models\PolicyException;
use Illuminate\Console\Command;

/**
 * Enforce exception expiry.
 *
 * An approved exception that has passed its expiration date is not silently
 * still valid. The status becomes Expired, which the register and the policy
 * view both show. Re-approval is a new decision, not a date roll-forward.
 */
class ExpirePolicyExceptions extends Command
{
    protected $signature = 'opengrc:exceptions:expire
                            {--dry-run : Report what would expire without changing status}';

    protected $description = 'Mark approved policy exceptions past their expiration date as expired';

    public function handle(): int
    {
        $query = PolicyException::query()
            ->where('status', PolicyExceptionStatus::Approved)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', today());

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No exceptions to expire.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$count} exception(s) would be marked expired.");

            return self::SUCCESS;
        }

        $updated = $query->update([
            'status' => PolicyExceptionStatus::Expired,
            'updated_at' => now(),
        ]);

        $this->info("Marked {$updated} exception(s) as expired.");

        return self::SUCCESS;
    }
}
