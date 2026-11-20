<?php

return [
    'navigation' => [
        'group' => 'Discovery',
    ],

    'scan' => [
        'navigation' => 'Discovery scans',
        'model' => 'Discovery scan',
        'plural' => 'Discovery scans',
        'columns' => [
            'status' => 'Status',
            'asset' => 'Asset',
            'profile' => 'Profile',
            'files_seen' => 'Files seen',
            'files_scanned' => 'Files scanned',
            'findings' => 'Findings',
            'synced_at' => 'Synced',
        ],
        'sections' => [
            'scan' => 'Scan',
            'source' => 'Source identifiers',
            'timing' => 'Timing',
        ],
        'entries' => [
            'remote_id' => 'Remote scan ID',
            'asset_id' => 'Remote asset ID',
            'profile_id' => 'Remote profile ID',
            'error' => 'Last error',
            'started_at' => 'Started',
            'finished_at' => 'Finished',
            'remote_created_at' => 'Created in engine',
            'synced_at' => 'Synced',
        ],
        'empty_heading' => 'No discovery scans yet',
        'empty_description' => 'Scans synced from the discovery console appear here, with file counts, findings and timestamps.',
    ],

    'finding' => [
        'navigation' => 'Discovery findings',
        'model' => 'Discovery finding',
        'plural' => 'Discovery findings',
        'columns' => [
            'severity' => 'Severity',
            'status' => 'Status',
            'asset' => 'Asset',
            'detector' => 'Detector',
            'category' => 'Category',
            'classification' => 'Classification',
            'confidence' => 'Confidence',
            'path' => 'Path',
            'synced_at' => 'Synced',
        ],
        'sections' => [
            'finding' => 'Finding',
            'source' => 'Source identifiers',
            'match' => 'Match',
            'timing' => 'Timing',
        ],
        'entries' => [
            'remote_id' => 'Remote finding ID',
            'remote_scan_id' => 'Remote scan ID',
            'asset_id' => 'Remote asset ID',
            'scan' => 'Local scan',
            'match_preview' => 'Match preview',
            'match_sha256' => 'Match SHA-256',
            'remote_created_at' => 'Created in engine',
            'synced_at' => 'Synced',
        ],
        'empty_heading' => 'No discovery findings',
        'empty_description' => 'Detector matches from synced discovery scans appear here. Only paths, previews and hashes are stored.',
    ],

    'status' => [
        'queued' => 'Queued',
        'pending' => 'Pending',
        'running' => 'Running',
        'completed' => 'Completed',
        'complete' => 'Complete',
        'partial' => 'Partial',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'canceled' => 'Canceled',
        'open' => 'Open',
        'acknowledged' => 'Acknowledged',
        'resolved' => 'Resolved',
        'suppressed' => 'Suppressed',
        'false_positive' => 'False positive',
        'ignored' => 'Ignored',
    ],

    'severity' => [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        'info' => 'Info',
        'informational' => 'Informational',
    ],

    'filters' => [
        'status' => 'Status',
        'severity' => 'Severity',
    ],

    'placeholders' => [
        'none' => '—',
        'not_recorded' => 'Not recorded',
    ],
];
