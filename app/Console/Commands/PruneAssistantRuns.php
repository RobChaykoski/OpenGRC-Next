<?php

namespace App\Console\Commands;

use App\Models\AiAssistantRun;
use Illuminate\Console\Command;

/**
 * Retention for the assistant audit trail (ADR 0046).
 *
 * The run log is accountability evidence, not programme data, and it contains
 * questions and narratives. Keep it long enough to review, then delete it on a
 * schedule rather than growing forever.
 */
class PruneAssistantRuns extends Command
{
    protected $signature = 'opengrc:assistant:prune
                            {--days=90 : Delete runs older than this many days}
                            {--dry-run : Report what would be deleted}';

    protected $description = 'Delete assistant run history older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = AiAssistantRun::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info("No assistant runs older than {$days} days.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$count} assistant run(s) would be deleted (older than {$days} days).");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} assistant run(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
