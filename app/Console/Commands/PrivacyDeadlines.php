<?php

namespace App\Console\Commands;

use App\Enums\PrivacyRequestStatus;
use App\Models\PrivacyRequest;
use Illuminate\Console\Command;

/**
 * Enforce the privacy response clock.
 *
 * An open request past its effective due date is Overdue. A request that is
 * merely due soon is reported but not changed: it is a warning, not a failure.
 */
class PrivacyDeadlines extends Command
{
    protected $signature = 'opengrc:privacy:deadlines
                            {--dry-run : Report overdue and due-soon requests without changing status}';

    protected $description = 'Mark open privacy requests past their deadline as overdue and report upcoming deadlines';

    public function handle(): int
    {
        $overdue = PrivacyRequest::query()->overdue();
        $overdueCount = (clone $overdue)->count();
        $dueSoonCount = PrivacyRequest::query()->dueSoon()->count();

        if ($overdueCount === 0 && $dueSoonCount === 0) {
            $this->info('No privacy requests are overdue or due soon.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$overdueCount} request(s) would be marked overdue; {$dueSoonCount} due in the next 7 days.");

            return self::SUCCESS;
        }

        $updated = $overdue->update([
            'status' => PrivacyRequestStatus::Overdue,
            'updated_at' => now(),
        ]);

        $this->info("Marked {$updated} request(s) overdue; {$dueSoonCount} due in the next 7 days.");

        return self::SUCCESS;
    }
}
