<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OSV.dev
    |--------------------------------------------------------------------------
    |
    | Read-only vulnerability queries. OSV is a public API; no token is needed.
    | The batch endpoint is the only POST, and it is a query, not a write.
    |
    */
    'base_url' => env('OSV_API_URL', 'https://api.osv.dev'),

    'timeout' => (int) env('OSV_TIMEOUT', 20),

    'batch_size' => (int) env('OSV_BATCH_SIZE', 500),
];
