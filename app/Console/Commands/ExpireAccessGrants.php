<?php

namespace App\Console\Commands;

use App\Models\AccessGrant;
use App\Services\Access\AccessGrantService;
use Illuminate\Console\Command;

/**
 * Enforce just-in-time grant expiry (ADR 0049).
 *
 * Expiry is the fail-safe, not the control. Revocation is event-driven; this
 * command handles the case where the event never arrived. An expired grant is
 * a terminal state, not a date roll-forward: the next request is a new decision.
 */
class ExpireAccessGrants extends Command
{
    protected $signature = 'opengrc:access:expire-grants
                            {--dry-run : Report what would expire without changing state}';

    protected $description = 'Expire just-in-time access grants past their expiry time';

    public function handle(AccessGrantService $service): int
    {
        $count = AccessGrant::query()->expirable()->count();
        $overdue = AccessGrant::query()->reviewOverdue()->count();

        if ($count === 0) {
            $this->info('No grants to expire.');
        } elseif ($this->option('dry-run')) {
            $this->info("{$count} grant(s) would be marked expired.");
        } else {
            $expired = $service->expire();
            $this->info("Marked {$expired} grant(s) as expired.");
        }

        if ($overdue > 0) {
            $this->warn("{$overdue} break-the-glass privacy review(s) are overdue.");
        }

        return self::SUCCESS;
    }
}
