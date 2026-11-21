<?php

return [
    'navigation' => 'Security architecture',
    'title' => 'Security architecture',
    'intro' => 'A current-state view of the canonical outcomes placed in the six NIST CSF 2.0 functions. It shows what is linked, evidenced, stale or missing. It does not infer a target state, a deployment location or a risk decision.',
    'framework' => 'Framework',
    'framework_help' => 'The canonical outcomes required by this framework. The mapping to a function is by risk domain and is defined in config/security_architecture.php.',
    'empty' => 'No canonical outcomes are available for this framework. Sync the catalogue and link the framework, then return to this page.',
    'current_only' => 'Current state only. A target state, roadmap decision, surface/location and deliberate non-implementation are programme records: put them in the risk treatment, acceptance record or architecture document. The page shows the evidence that exists, not a compliance conclusion.',
    'open_matrix' => 'Open assurance matrix',
    'counts' => [
        'outcomes' => 'Outcomes',
        'evidenced' => 'Evidenced',
        'documented' => 'Documented only',
        'stale' => 'Stale',
        'failing' => 'Failing',
        'gap' => 'Gap',
    ],
    'states' => [
        'evidenced' => 'Evidenced',
        'documented' => 'Documented only',
        'stale' => 'Stale',
        'failing' => 'Failing',
        'gap' => 'Gap',
    ],
    'state_help' => [
        'evidenced' => 'At least one control and current evidence or validation.',
        'documented' => 'A control and policy exist, but no evidence or validation supports the outcome.',
        'stale' => 'Evidence exists but is older than the outcome frequency allows.',
        'failing' => 'The latest validation failed.',
        'gap' => 'No reviewed control, or no evidence and no policy.',
    ],
    'columns' => [
        'outcome' => 'Outcome',
        'priority' => 'Priority',
        'controls' => 'Controls',
        'evidence' => 'Evidence',
        'state' => 'State',
        'latest' => 'Latest evidence',
        'confidence' => 'Confidence',
    ],
    'confidence' => [
        'none' => 'None',
        'documentation' => 'Documentation (20)',
        'manual' => 'Manual upload (50)',
        'api' => 'API collected (80)',
        'validation' => 'Validation (100)',
    ],
    'functions' => [
        'govern' => [
            'label' => 'Govern',
            'description' => 'Risk strategy, policy, roles, supply chain and oversight.',
        ],
        'identify' => [
            'label' => 'Identify',
            'description' => 'Assets, risk assessment, improvement and the current risk picture.',
        ],
        'protect' => [
            'label' => 'Protect',
            'description' => 'Identity, access, data, platforms, applications and resilience of prevention.',
        ],
        'detect' => [
            'label' => 'Detect',
            'description' => 'Monitoring, logging, detection and continuous analysis.',
        ],
        'respond' => [
            'label' => 'Respond',
            'description' => 'Incident management, analysis, reporting and communication.',
        ],
        'recover' => [
            'label' => 'Recover',
            'description' => 'Recovery planning, restoration and post-incident improvement.',
        ],
    ],
];
