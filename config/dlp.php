<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data loss prevention
    |--------------------------------------------------------------------------
    |
    | The native scanner is always available. Presidio is optional; point
    | PRESIDIO_URL at a sidecar to add its PII recognisers. A scanner outage
    | falls back to the native patterns rather than silently passing content.
    |
    */

    'presidio_url' => env('PRESIDIO_URL'),
    'presidio_timeout' => (int) env('PRESIDIO_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | AI egress
    |--------------------------------------------------------------------------
    |
    | These types are never sent to an AI provider, regardless of provider or
    | settings. HR, special-category, and credential data are a hard boundary.
    |
    */

    'ai' => [
        'block_types' => ['credential', 'special_category', 'hr'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Uploads are scanned for text-extractable formats. Credentials are blocked;
    | HR and special-category content is flagged for review because a background
    | check or audit artifact may legitimately contain it and must be stored
    | securely rather than rejected.
    |
    */

    'uploads' => [
        'enabled' => env('DLP_UPLOAD_SCAN', true),
        'block_types' => ['credential'],
        'max_scan_bytes' => (int) env('DLP_MAX_SCAN_BYTES', 2097152),
    ],

];
