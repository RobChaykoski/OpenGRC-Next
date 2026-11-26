<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Control-test reminders
    |--------------------------------------------------------------------------
    |
    | How many days before a test is due the owner is reminded. The report
    | command keeps its own --days option; this is the scheduled default.
    |
    */

    'reminder_window_days' => (int) env('CONTROL_TEST_REMINDER_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Escalation
    |--------------------------------------------------------------------------
    |
    | Escalation is opt-in. Set the number of days a test must be overdue before
    | administrators are notified. Zero disables escalation: the owner still
    | receives the due and overdue reminders, and the queue stays visible, but
    | nobody is escalated to.
    |
    | An assigned test is escalated once per test date. Unowned tests that pass
    | the threshold are sent to administrators as one weekly digest, so a lapse
    | in ownership is visible without becoming a daily notification.
    |
    */

    'escalation' => [
        'after_days_overdue' => (int) env('CONTROL_TEST_ESCALATION_AFTER_DAYS', 0),
    ],

];
