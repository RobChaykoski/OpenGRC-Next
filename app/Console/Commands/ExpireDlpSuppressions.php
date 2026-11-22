<?php

namespace App\Console\Commands;

use App\Models\DlpSuppression;
use App\Services\Dlp\DlpSuppressionService;
use Illuminate\Console\Command;

/**
 * Enforce DLP suppression expiry (ADR 0053).
 *
 * A suppression is an acceptance with a date. The command is the fail-safe for
 * the date nobody acted on; continuing the acceptance is a new request.
 */
class ExpireDlpSuppressions extends Command
{
    protected $signature = 'opengrc:dlp:expire-suppressions
                            {--dry-run : Report what would expire without changing state}';

    protected $description = 'Expire DLP suppressions past their expiry time';

    public function handle(DlpSuppressionService $service): int
    {
        $count = DlpSuppression::query()->expirable()->count();

        if ($count === 0) {
            $this->info('No suppressions to expire.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$count} suppression(s) would be marked expired.");

            return self::SUCCESS;
        }

        $expired = $service->expire();
        $this->info("Marked {$expired} suppression(s) as expired.");

        return self::SUCCESS;
    }
}
