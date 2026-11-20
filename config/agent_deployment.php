<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agent deployment guidance
    |--------------------------------------------------------------------------
    |
    | These values generate copyable install commands on the missed-assets
    | page. They are not secrets; enrollment passwords and API tokens must come
    | from the operator's secret store and are never rendered here.
    |
    */
    'wazuh' => [
        'manager' => env('WAZUH_MANAGER', ''),
        'registration_server' => env('WAZUH_REGISTRATION_SERVER', ''),
        'agent_group' => env('WAZUH_AGENT_GROUP', 'default'),
        'apt_repository' => env('WAZUH_APT_REPOSITORY', 'https://packages.wazuh.com/4.x/apt/'),
        'yum_repository' => env('WAZUH_YUM_REPOSITORY', 'https://packages.wazuh.com/4.x/yum/'),
        'windows_msi_url' => env('WAZUH_WINDOWS_MSI_URL', ''),
    ],

    'patchmon' => [
        'server_url' => env('PATCHMON_SERVER_URL', ''),
        // Optional template. Use {{server_url}} and {{agent_group}} placeholders.
        'install_command' => env('PATCHMON_INSTALL_COMMAND', ''),
    ],

    'fleet' => [
        'url' => env('FLEET_URL', ''),
    ],
];
