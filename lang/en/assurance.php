<?php

return [
    'navigation' => [
        'label' => 'Assurance matrix',
        'group' => 'Foundations',
    ],
    'page' => [
        'title' => 'Assurance matrix',
    ],
    'framework' => [
        'label' => 'Framework',
        'help' => 'Choose a framework to see the outcomes it requires and what currently supports them. This page reports reviewed links and evidence only; it is not a compliance score. Outcomes with no linked control are shown as work to review, not as failures.',
        'none' => 'No frameworks in the catalogue. Run php artisan opengrc:catalog:sync.',
    ],
    'columns' => [
        'code' => 'Code',
        'outcome' => 'Outcome',
        'category' => 'Category',
        'priority' => 'Priority',
        'automation' => 'Automation',
        'controls' => 'Controls',
        'policies' => 'Policies',
        'evidence' => 'Evidence',
        'status' => 'Status',
    ],
    'priority' => [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],
    'automation' => [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],
    'status' => [
        'covered' => 'Linked + evidenced',
        'no_evidence' => 'Evidence missing',
        'no_control' => 'No reviewed control',
        'evidence_stale' => 'Evidence stale',
        'validation_failing' => 'Validation failing',
    ],
    'filters' => [
        'gaps' => 'Gaps only',
        'only_gaps' => 'Only outcomes needing review',
        'only_gaps_helper' => 'Shows outcomes with no linked control, no evidence, stale evidence, or a failing validation.',
    ],
    'score' => [
        'heading' => 'Assurance score',
        'total' => 'Score',
        'counts' => ':reviewed of :outcomes outcomes reviewed',
        'trend' => ':change over :days days',
        'no_trend' => 'No trend yet — the daily snapshot builds it.',
        'weight' => ':weight% weight',
        'help' => 'A weighted view of reviewed links and evidence quality. It is not a compliance percentage; read the components before acting on it.',
        'unavailable' => 'No score yet — this framework has no outcomes in the catalogue.',
        'components' => [
            'reviewed' => 'Reviewed',
            'evidence' => 'Evidence',
            'quality' => 'Quality',
            'automation' => 'Automation',
            'monitoring' => 'Monitoring',
        ],
    ],
    'not_set' => 'Not set',
    'latest_evidence' => 'Latest evidence: :date',
    'empty' => [
        'heading' => 'No outcomes for this framework',
        'description' => 'Import the framework or extend config/framework_scoping.php, then run opengrc:catalog:sync.',
    ],
];
