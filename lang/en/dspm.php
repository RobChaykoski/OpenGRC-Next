<?php

return [
    'navigation' => 'Data posture',
    'title' => 'DLP & DSPM',

    'intro' => [
        'dlp_title' => 'DLP: policy at the point of use',
        'dlp_body' => 'DLP stops sensitive data leaving through email, uploads, endpoints or AI prompts. The useful measures are blocks, flags, false positives and time to respond — not the number of rules.',
        'dspm_title' => 'DSPM: posture at rest',
        'dspm_body' => 'DSPM finds where sensitive data lives, who can reach it, whether it is protected, who owns it, and whether it should still exist. The useful measures are coverage, classification, exposure, ownership and retention.',
        'why_title' => 'Why this is valuable',
        'why_body' => 'Most tools watch traffic or endpoints. Very few people can connect discovery, classification, access, policy and compliance evidence. That is the niche skill employers pay for: turning data into a defensible decision.',
    ],

    'callout' => [
        'title' => 'Unknown is not clean',
        'body' => 'A store that was never scanned is a coverage gap, not a zero. An agent that stopped reporting makes its control unknown, not effective. Read freshness before you read a green status.',
    ],

    'metrics' => [
        'open_findings' => 'Open sensitive findings',
        'critical_high' => ':count critical or high',
        'coverage' => 'Classification coverage',
        'coverage_detail' => ':scanned of :total assets have a discovery scan',
        'coverage_unknown' => 'No assets or scans yet',
        'freshness' => 'Mirror freshness',
        'last_sync' => 'Last sync :date',
        'never' => 'Never',
        'run_sync' => 'Run opengrc:discovery:sync',
        'unmapped' => 'Unmapped findings',
        'unmapped_detail' => 'Findings without an asset name cannot be reviewed in context',
    ],

    'charts' => [
        'category_heading' => 'Open findings by data category',
        'category_description' => 'Where sensitive data is concentrated',
        'aging_heading' => 'Open findings by age',
        'aging_description' => 'Aging is risk: older findings need a decision',
        'no_findings' => 'No open findings',
    ],

    'category' => [
        'uncategorized' => 'Uncategorised',
        'pii' => 'Personal information',
        'phi' => 'Health information',
        'pci' => 'Cardholder data',
        'secrets' => 'Secrets',
        'credentials' => 'Credentials',
        'financial' => 'Financial records',
        'personal_data' => 'Personal data',
    ],

    'aging' => [
        'last_7' => '0–7 days',
        'days_7_30' => '8–30 days',
        'days_31_90' => '31–90 days',
        'older' => 'More than 90 days',
    ],

    'exposure' => [
        'heading' => 'Assets with the most open findings',
        'description' => 'Where exposure concentrates. Start with the top row, not the total.',
        'asset' => 'Asset',
        'open' => 'Open',
        'critical_high' => 'Critical / high',
        'oldest' => 'Oldest open',
        'last_synced' => 'Last synced',
        'unmapped' => 'Unmapped asset',
        'empty_heading' => 'No open findings',
        'empty_description' => 'A clean table here is only meaningful when the discovery mirror is fresh and coverage is complete.',
    ],

    'catalog' => [
        'heading' => 'Data catalog governance',
        'description' => 'Ownership and classification coverage from OpenMetadata. Aggregates only; no table names or rows.',
        'services' => 'Catalog services',
        'services_detail' => ':tables table(s) catalogued',
        'owners' => 'Owner coverage',
        'owners_detail' => ':owned of :total tables have an owner',
        'classification' => 'Classification coverage',
        'classification_detail' => ':classified of :total tables are classified',
        'freshness' => 'Catalog freshness',
        'last_collected' => 'Last collected :date',
        'never' => 'Never collected',
        'not_configured' => 'No catalog evidence yet. Configure an OpenMetadata source and run collection.',
    ],

    'dlp' => [
        'blocked' => 'Blocked (7 days)',
        'blocked_description' => 'Policy stopped the action',
        'flagged' => 'Flagged (7 days)',
        'flagged_description' => 'Allowed for review or context',
        'critical_high' => 'Critical or high (7 days)',
        'critical_high_description' => 'Prioritise these for response',
        'no_events' => 'No DLP events in the last 7 days',
    ],

    'learning' => [
        'read_title' => 'How to read this page',
        'read_body' => 'Read left to right: coverage, then classification, then exposure, then response. A low finding count with low coverage is not a clean programme.',
        'read_items' => [
            'Coverage tells you whether you can trust the rest of the page.',
            'Classification tells you whether the data is understood, not only found.',
            'Exposure tells you where a leak would hurt most.',
            'Aging tells you whether the programme is actually reducing risk.',
            'Freshness tells you whether the numbers describe today or last month.',
        ],
        'employer_title' => 'What employers ask',
        'employer_body' => 'Hiring managers rarely ask for a tool tutorial. They ask whether you can find the data, explain the risk and show the evidence.',
        'employer_items' => [
            'Where is our sensitive data, and how do you know?',
            'Who owns each sensitive store, and who can access it?',
            'Which exposure would matter most to the business, and why?',
            'What did you fix, how fast, and how do you prove it?',
            'Which frameworks and controls does this evidence support?',
            'How do you distinguish a clean result from an unscanned one?',
        ],
    ],

    'next' => [
        'title' => 'Turn the page into decisions',
        'body' => 'The dashboard is not the deliverable. The decisions are: retire, restrict, encrypt, assign an owner, tune a detector, or add a control test.',
        'items' => [
            'Retire stale stores that no longer serve a purpose.',
            'Restrict access where sensitive data is concentrated.',
            'Encrypt or tokenise the highest-risk stores first.',
            'Assign an owner and criticality to every sensitive store.',
            'Tune detectors with high false-positive rates.',
            'Map the evidence to a framework outcome and add a validation rule.',
        ],
    ],

    'links' => [
        'findings' => 'Review discovery findings',
        'scans' => 'Open discovery scans',
        'assets' => 'Open the asset register',
        'frameworks' => 'Open the assurance matrix',
        'dlp' => 'Open DLP findings',
    ],

    'empty' => [
        'no_findings' => 'No open findings',
    ],
];
