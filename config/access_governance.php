<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Classification freshness
    |--------------------------------------------------------------------------
    |
    | A classification older than this is stale. A stale tag is a coverage gap,
    | not a clean result: the evaluator masks rather than trusting it.
    |
    */

    'classification' => [
        'stale_after_days' => (int) env('ACCESS_CLASSIFICATION_STALE_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grants
    |--------------------------------------------------------------------------
    |
    | Durations are minutes. The break-glass maximum is a backstop, not the
    | control: revocation is event-driven and expiry is what happens when the
    | event never arrives.
    |
    */

    'grants' => [
        'max_duration_minutes' => (int) env('ACCESS_GRANT_MAX_MINUTES', 480),
        'break_glass_max_minutes' => (int) env('ACCESS_BREAK_GLASS_MAX_MINUTES', 30),
        'break_glass_review_days' => (int) env('ACCESS_BREAK_GLASS_REVIEW_DAYS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposure
    |--------------------------------------------------------------------------
    |
    | The weight of sensitive data consumed per subject in a rolling window.
    | Weights are relative, not currency. Thresholds escalate the decision.
    |
    */

    'exposure' => [
        'window_minutes' => (int) env('ACCESS_EXPOSURE_WINDOW_MINUTES', 60),

        'weights' => [
            'public' => 0,
            'internal' => 1,
            'confidential' => 3,
            'restricted' => 8,
        ],

        'thresholds' => [
            'review' => (int) env('ACCESS_EXPOSURE_REVIEW', 25),
            'step_up' => (int) env('ACCESS_EXPOSURE_STEP_UP', 50),
            'block' => (int) env('ACCESS_EXPOSURE_BLOCK', 100),
        ],
    ],

];
