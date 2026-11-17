<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transport security
    |--------------------------------------------------------------------------
    |
    | HSTS is only sent in production over HTTPS. Keep max-age conservative at
    | first (180 days), and only add includeSubDomains once every subdomain is
    | HTTPS-only — including development and internal hosts.
    |
    */

    'hsts' => [
        'enabled' => env('HSTS_ENABLED', true),
        'max_age' => (int) env('HSTS_MAX_AGE', 15552000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Secure cookies
    |--------------------------------------------------------------------------
    |
    | When the application URL is HTTPS in production, session cookies are forced
    | secure regardless of the environment file. A cookie that can travel over
    | HTTP is a cookie that can be stolen.
    |
    */

    'force_secure_cookies' => env('FORCE_SECURE_COOKIES', true),

];
