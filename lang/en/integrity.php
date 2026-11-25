<?php

return [
    'check' => [
        'evidence' => 'Evidence ledger',
        'audit' => 'Audit ledger',
    ],

    'summary' => [
        'not_installed' => ':check is not installed yet; run migrations.',
        'unreadable' => ':check could not be read; see the application log.',
        'evidence_passed' => 'Checked :count evidence record(s); chain intact.',
        'evidence_warned' => 'Checked :count evidence record(s); :warnings warning(s).',
        'evidence_failed' => 'Checked :count evidence record(s); :problems problem(s) found.',
        'audit_passed' => 'Audit ledger intact across :total entries.',
        'audit_pruned' => ':missing mirrored activity row(s) were pruned; the ledger entries remain.',
        'audit_broken' => 'Audit ledger chain is broken at entry #:entry.',
        'audit_altered' => ':count activity row(s) no longer match the audit ledger.',
    ],

    'command' => [
        'unknown_check' => 'Unknown check [:check]. Use evidence, audit or all.',
        'pass' => 'PASS',
        'warn' => 'WARN',
        'fail' => 'FAIL',
        'detail' => 'Checked at :time in :duration ms.',
    ],

    'widget' => [
        'title' => 'Record integrity',
        'value' => [
            'pass' => 'Intact',
            'warn' => 'Attention',
            'fail' => 'Failed',
            'never' => 'Not run',
        ],
        'description' => [
            'never' => 'Run php artisan opengrc:integrity:verify',
            'last_run' => 'Evidence and audit chains; last verified :time',
            'stale' => 'Last verified :time; the schedule may have stopped',
            'issue' => ':summary',
        ],
    ],
];
