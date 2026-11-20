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
