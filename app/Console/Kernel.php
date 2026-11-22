<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Bound growth of personal data in the activity log. The retention
        // window is delete_records_older_than_days in config/activitylog.php.
        $schedule->command('activitylog:clean')->daily();

        // Generate recurring checklists daily at 6:00 AM
        $schedule->command('checklists:generate-recurring')->dailyAt('06:00');

        // Record one assurance snapshot per framework per day so the score has
        // a trend; re-running the command updates the same day's row.
        $schedule->command('opengrc:assurance:snapshot')->dailyAt('06:15');

        // An approved exception past its expiry is no longer valid. Do this
        // before the working day so the register and policy views are honest.
        $schedule->command('opengrc:exceptions:expire')->dailyAt('00:10');

        // Explicit report expiry, separate from a report's covered period.
        $schedule->command('opengrc:attestations:expire')->dailyAt('00:15');

        // Privacy response clocks. A request that goes overdue is a compliance
        // event, not a quiet date change.
        $schedule->command('opengrc:privacy:deadlines')->dailyAt('00:20');

        // Machine credentials: surface expiry and rotation without touching the
        // secret store. Rotation stays a human action.
        $schedule->command('opengrc:identities:review')->dailyAt('00:25');

        // A risk acceptance is a dated decision, not a permanent state.
        $schedule->command('opengrc:risks:acceptance')->dailyAt('00:30');

        // A pending control attestation past its due date is no longer a valid
        // confirmation; mark it expired without changing the owner's statement.
        $schedule->command('opengrc:controls:attestations:expire')->dailyAt('00:35');

        // Just-in-time grants are minute-scale. Revocation is event-driven; this
        // is the fail-safe when the event never arrives (ADR 0049).
        $schedule->command('opengrc:access:expire-grants')->everyFiveMinutes();
        $schedule->command('opengrc:dlp:expire-suppressions')->dailyAt('00:40');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
