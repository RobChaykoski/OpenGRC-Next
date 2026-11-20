<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Read-only console mirror. The token is a scoped reader credential for the
    | OpenDiscoveryEngine console API; it is never sent anywhere else.
    */
    'discovery' => [
        'base_url' => env('DISCOVERY_CONSOLE_URL'),
        'token' => env('DISCOVERY_CONSOLE_TOKEN'),
        'tenant' => env('DISCOVERY_CONSOLE_TENANT'),
        'timeout' => (int) env('DISCOVERY_CONSOLE_TIMEOUT', 15),
        'stale_after_days' => (int) env('DISCOVERY_CONSOLE_STALE_AFTER_DAYS', 2),
    ],

];
