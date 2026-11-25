<?php

namespace App\Console\Commands;

use App\Services\ControlTestingReminder;
use Illuminate\Console\Command;

/**
 * Send the in-app reminders the control testing report cannot.
 *
 * This is the scheduled companion to `opengrc:controls:testing`: the report
 * lists the queue for a person to read, this command puts the same due and
 * overdue items in each owner's notification bell once per stage and test date.
 */
class NotifyControlTesting extends Command
{
    protected $signature = 'opengrc:controls:testing:notify
                            {--days=7 : Include tests due within this many days}
                            {--dry-run : Report what would be sent without writing}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Notify implementation owners when a control test is due or overdue';

    public function handle(ControlTestingReminder $reminder): int
    {
        $days = max(0, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $report = $reminder->send($days, $dryRun);

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_merge(['generated_at' => now()->toIso8601String()], $report),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Window', $days === 0 ? 'today' : 'next '.$days.' day(s)'],
                ['Due', $report['due']],
                ['Overdue', $report['overdue']],
                ['Notified', $report['notified']],
                ['Skipped (already reminded)', $report['skipped']],
                ['Unassigned (not routed)', $report['unassigned']],
                ['Dry run', $dryRun ? 'yes' : 'no'],
            ]
        );

        if ($report['unassigned'] > 0) {
            $this->warn("{$report['unassigned']} due or overdue test(s) have no implementation owner and were not routed.");
        }

        if ($report['notified'] === 0 && $report['skipped'] === 0 && $report['unassigned'] === 0) {
            $this->info('Nothing to remind.');
        } elseif ($dryRun) {
            $this->info('Dry run: nothing was written.');
        } else {
            $this->info('Reminders sent.');
        }

        return self::SUCCESS;
    }
}
