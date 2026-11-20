<?php

return [
    /*
    |--------------------------------------------------------------------------
    | deps.dev
    |--------------------------------------------------------------------------
    |
    | Optional read-only enrichment: package licenses and OpenSSF Scorecard
    | context. No token is required. Only public packages are covered.
    |
    */
    'base_url' => env('DEPS_DEV_URL', 'https://api.deps.dev'),

    'timeout' => (int) env('DEPS_DEV_TIMEOUT', 20),
];
