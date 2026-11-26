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
                            {--days= : Include tests due within this many days; defaults to config}
                            {--dry-run : Report what would be sent without writing}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Notify implementation owners when a control test is due or overdue';

    public function handle(ControlTestingReminder $reminder): int
    {
        $days = $this->option('days');
        $days = ($days === null || $days === '')
            ? max(0, (int) config('control_testing.reminder_window_days', 7))
            : max(0, (int) $days);

        $dryRun = (bool) $this->option('dry-run');
        $report = $reminder->send($days, $dryRun);

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_merge(['generated_at' => now()->toIso8601String()], $report),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        $escalation = $report['escalation_after_days'] === 0
            ? 'off'
            : 'after '.$report['escalation_after_days'].' overdue day(s)';

        $this->table(
            ['Field', 'Value'],
            [
                ['Window', $days === 0 ? 'today' : 'next '.$days.' day(s)'],
                ['Due', $report['due']],
                ['Overdue', $report['overdue']],
                ['Notified', $report['notified']],
                ['Would notify (dry run)', $report['would_notify']],
                ['Skipped (already reminded)', $report['skipped']],
                ['Unassigned (not routed)', $report['unassigned']],
                ['Escalation', $escalation],
                ['Escalated', $report['escalated']],
                ['Would escalate (dry run)', $report['would_escalate']],
                ['Escalation skipped', $report['escalation_skipped']],
                ['Dry run', $dryRun ? 'yes' : 'no'],
            ]
        );

        if ($report['unassigned'] > 0) {
            $this->warn("{$report['unassigned']} due or overdue test(s) have no implementation owner and were not routed to an owner.");
        }

        if ($report['escalation_after_days'] > 0 && $report['escalation_admins'] === 0) {
            $this->warn('Escalation is enabled but no administrator account exists to receive it.');
        }

        if ($report['notified'] === 0 && $report['escalated'] === 0 && $report['would_notify'] === 0 && $report['would_escalate'] === 0 && $report['skipped'] === 0 && $report['unassigned'] === 0) {
            $this->info('Nothing to remind.');
        } elseif ($dryRun) {
            $this->info('Dry run: nothing was written.');
        } else {
            $this->info('Reminders sent.');
        }

        return self::SUCCESS;
    }
}
