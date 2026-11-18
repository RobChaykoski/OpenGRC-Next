<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Finding remediation targets
    |--------------------------------------------------------------------------
    |
    | How many days an automated finding may stay open, by severity, before it
    | is overdue. These are defaults for a small programme; adjust them in your
    | own config and record the change in your risk methodology.
    |
    */

    'finding_due_days' => [
        'critical' => 7,
        'high' => 14,
        'medium' => 30,
        'low' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale collection threshold
    |--------------------------------------------------------------------------
    |
    | An enabled source whose last successful collection is older than this is
    | reported as stale by `opengrc:doctor`. Set it above your slowest schedule
    | so a normal quiet period is not treated as an outage.
    |
    */

    'stale_after_days' => 7,

];
