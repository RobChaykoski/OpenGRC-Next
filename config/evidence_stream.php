<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Streaming evidence
    |--------------------------------------------------------------------------
    |
    | The push endpoint is off by default. Enabling it is a deliberate change:
    | it is a public write path and needs its own credential, rate limit and
    | negative tests. See ADR 0054 and docs/integrations/streaming-evidence.md.
    |
    */

    'enabled' => (bool) env('EVIDENCE_STREAM_ENABLED', false),

    /*
    | The producer signs `t.body` with its stream secret. A request outside the
    | tolerance is rejected, which is what stops a captured request being
    | replayed tomorrow.
    */
    'tolerance_seconds' => (int) env('EVIDENCE_STREAM_TOLERANCE', 300),

    /*
    | An event time further from now than this is accepted but flagged as late.
    | A clock that is wrong is a data-quality problem, not a reason to lose the
    | event.
    */
    'max_event_skew_hours' => (int) env('EVIDENCE_STREAM_MAX_SKEW_HOURS', 24),

];
