<?php

return [
    'navigation' => 'Missed assets',
    'title' => 'Missed assets and agent coverage',

    'intro' => [
        'title' => 'Who is not covered, and what to do next',
        'body' => 'This report compares the OpenGRC asset register with the latest Wazuh, PatchMon and discovery evidence. It is deliberately read-only: the recommendation tells you whether to create the asset, assign an owner, deploy an agent or refresh a scan.',
    ],

    'summary' => [
        'documented' => 'Documented assets',
        'documented_detail' => ':total in the register',
        'wazuh' => 'Wazuh coverage',
        'patchmon' => 'PatchMon coverage',
        'discovery' => 'Discovery coverage',
        'unowned' => 'Unowned',
        'unowned_detail' => 'Assets without an accountable owner',
        'critical' => 'Critical / high gaps',
        'critical_detail' => 'Fix these first',
        'percent' => ':percent%',
    ],

    'filters' => [
        'gap' => 'Gap type',
        'all' => 'All gaps',
        'search' => 'Search assets',
        'search_placeholder' => 'Hostname or asset name',
    ],

    'table' => [
        'title' => 'Coverage gaps',
        'priority' => 'Priority',
        'asset' => 'Asset',
        'sources' => 'Sources',
        'owner' => 'Owner',
        'gaps' => 'Gaps',
        'recommendation' => 'Recommendation',
        'last_seen' => 'Last seen',
        'assigned' => 'Assigned',
        'unassigned' => 'Unassigned',
        'empty_heading' => 'No matching gaps',
        'empty_description' => 'Either the filter is narrow or every asset has the expected coverage.',
    ],

    'priority' => [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],

    'gaps' => [
        'undocumented' => 'Undocumented',
        'unowned' => 'Unowned',
        'missing_wazuh' => 'No Wazuh agent',
        'missing_patchmon' => 'No PatchMon agent',
        'unscanned' => 'Not scanned',
        'stale_wazuh' => 'Wazuh stale',
        'stale_patchmon' => 'PatchMon stale',
        'stale_discovery' => 'Discovery stale',
    ],

    'sources' => [
        'opengrc' => 'OpenGRC',
        'wazuh' => 'Wazuh',
        'patchmon' => 'PatchMon',
        'discovery' => 'Discovery',
        'netbox' => 'NetBox',
        'ralph' => 'Ralph',
        'fleet' => 'Fleet',
        'configured' => 'Configured',
        'not_configured' => 'Not configured',
        'not_integrated' => 'Not integrated',
        'stale' => 'stale',
    ],

    'deploy' => [
        'title' => 'Deploy the missing agent',
        'body' => 'Run the command for the host platform. Commands are generated from the Wazuh source and deployment settings; they contain no passwords or tokens.',
        'copy' => 'Copy',
        'not_configured' => 'Not configured. Set the deployment environment variables or use the vendor installer.',
        'only_writers' => 'Ask an administrator or editor for deployment commands. This report is readable by every role.',
        'tabs' => [
            'debian' => 'Linux (Debian/Ubuntu)',
            'rhel' => 'Linux (RHEL)',
            'windows' => 'Windows',
            'patchmon' => 'PatchMon',
            'fleet' => 'Fleet',
        ],
        'notes' => 'Before you install',
    ],

    'source_status' => [
        'title' => 'Source status',
        'body' => 'A source that is not configured is not evidence of coverage. The report only evaluates the sources OpenGRC can actually read.',
    ],

    'links' => [
        'posture' => 'Open the data posture dashboard',
        'assets' => 'Open the asset register',
        'findings' => 'Review discovery findings',
    ],
];
