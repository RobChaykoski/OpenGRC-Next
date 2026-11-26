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

    /*
    | OpenBao secret-store metadata. The token is a read-only metadata reader,
    | never a root token, and the KV mount is where the NHI references point.
    */
    'openbao' => [
        'base_url' => env('OPENBAO_URL'),
        'token' => env('OPENBAO_TOKEN'),
        'kv_mount' => env('OPENBAO_KV_MOUNT', 'secret'),
        'timeout' => (int) env('OPENBAO_TIMEOUT', 15),
    ],

    /*
    | Authentik OIDC. The base URL is the IdP root; the provider package appends
    | /application/o/authorize, /token, and /userinfo. Leave any value blank to
    | keep the button off the login screen.
    */
    'authentik' => [
        'base_url' => env('AUTHENTIK_BASE_URL'),
        'client_id' => env('AUTHENTIK_CLIENT_ID'),
        'client_secret' => env('AUTHENTIK_CLIENT_SECRET'),
        'redirect' => env('AUTHENTIK_REDIRECT_URI'),
    ],

];
