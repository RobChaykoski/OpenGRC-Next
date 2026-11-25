<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retention of verification runs
    |--------------------------------------------------------------------------
    |
    | Integrity checks run on a schedule and keep their history so a gap is
    | visible. Old runs are pruned after this many days; the chains they checked
    | are untouched. Set to 0 to keep every run indefinitely.
    |
    */

    'retention_days' => (int) env('INTEGRITY_RETENTION_DAYS', 400),

    /*
    |--------------------------------------------------------------------------
    | Stale schedule threshold
    |--------------------------------------------------------------------------
    |
    | The dashboard warns when the newest run for either chain is older than
    | this. The default is a day plus slack for a slow or skipped run. A
    | scheduled check that has stopped is a blind spot, not a pass.
    |
    */

    'stale_after_hours' => (int) env('INTEGRITY_STALE_AFTER_HOURS', 26),

];
