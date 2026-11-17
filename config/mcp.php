<?php

/*
|--------------------------------------------------------------------------
| MCP / OAuth settings
|--------------------------------------------------------------------------
|
| Dynamic client registration creates OAuth clients without a human decision.
| That is convenient for hosted assistants and dangerous for a self-hosted
| system of record: anyone reachable on the network can mint a client.
|
| Default posture: registration is disabled. When enabled, it requires an
| authenticated user unless explicitly opened, and redirect URIs must match the
| configured domains.
|
*/

return [

    'dynamic_client_registration' => env('MCP_DYNAMIC_CLIENT_REGISTRATION', false),

    'registration_requires_auth' => env('MCP_REGISTRATION_REQUIRES_AUTH', true),

    /*
     * Comma-separated list of allowed redirect URI prefixes. Leave empty to allow
     * no automatic registrations. Use '*' only on a host you control.
     */
    'redirect_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_REDIRECT_DOMAINS', ''))
    ))),

];
