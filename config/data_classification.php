<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Classification levels
    |--------------------------------------------------------------------------
    |
    | The organisation-wide scale. Industry labels map onto one of these so a
    | dashboard can compare a PHI record with a PCI record without inventing a
    | new scale per industry.
    |
    */
    'levels' => [
        'public' => ['label' => 'Public', 'rank' => 1],
        'internal' => ['label' => 'Internal', 'rank' => 2],
        'confidential' => ['label' => 'Confidential', 'rank' => 3],
        'restricted' => ['label' => 'Restricted', 'rank' => 4],
    ],

    /*
    |--------------------------------------------------------------------------
    | Common labels
    |--------------------------------------------------------------------------
    |
    | Labels that apply in every industry. They are selectable but do not by
    | themselves imply a regulatory regime.
    |
    */
    'common' => [
        ['code' => 'personal_information', 'label' => 'Personal Information', 'level' => 'confidential', 'description' => 'Information about an identifiable individual.'],
        ['code' => 'pii', 'label' => 'Personally Identifiable Information (PII)', 'level' => 'confidential', 'description' => 'Identifiers that can single out a person.'],
        ['code' => 'confidential_business', 'label' => 'Confidential Business Information', 'level' => 'confidential', 'description' => 'Commercial terms, plans, or internal analysis.'],
        ['code' => 'intellectual_property', 'label' => 'Intellectual Property', 'level' => 'confidential', 'description' => 'Source code, designs, research, or trade secrets.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry labels
    |--------------------------------------------------------------------------
    |
    | A label can belong to several industries. Keep the label vocabulary shared
    | where the legal concept is the same (for example PCI cardholder data in
    | retail and financial services); add a new label only when the obligation
    | or handling rule is actually different.
    |
    */
    'industries' => [
        'healthcare' => [
            'label' => 'Healthcare',
            'labels' => [
                ['code' => 'phi', 'label' => 'Protected Health Information (PHI)', 'level' => 'restricted', 'description' => 'Health information linked to an individual, including Ontario PHIPA and US HIPAA data.'],
                ['code' => 'clinical_records', 'label' => 'Clinical Records', 'level' => 'restricted', 'description' => 'Assessments, notes, diagnoses, and treatment history.'],
                ['code' => 'health_research', 'label' => 'Health Research Data', 'level' => 'restricted', 'description' => 'De-identified or coded research datasets.'],
            ],
        ],
        'financial_services' => [
            'label' => 'Financial Services',
            'labels' => [
                ['code' => 'cardholder_data', 'label' => 'Cardholder Data (PCI)', 'level' => 'restricted', 'description' => 'Primary account numbers and sensitive authentication data.'],
                ['code' => 'financial_records', 'label' => 'Financial Records', 'level' => 'confidential', 'description' => 'Statements, ledgers, and transaction records.'],
                ['code' => 'kyc_records', 'label' => 'KYC / AML Records', 'level' => 'restricted', 'description' => 'Identity verification, beneficial ownership, and suspicious-activity records.'],
            ],
        ],
        'education' => [
            'label' => 'Education',
            'labels' => [
                ['code' => 'student_records', 'label' => 'Student Records (FERPA)', 'level' => 'restricted', 'description' => 'Admissions, grades, discipline, and financial aid records.'],
                ['code' => 'research_data', 'label' => 'Research Data', 'level' => 'confidential', 'description' => 'Unpublished or participant-linked research data.'],
            ],
        ],
        'government' => [
            'label' => 'Government',
            'labels' => [
                ['code' => 'protected_a', 'label' => 'Protected A', 'level' => 'confidential', 'description' => 'Information whose disclosure could cause injury to an individual or organisation.'],
                ['code' => 'protected_b', 'label' => 'Protected B', 'level' => 'restricted', 'description' => 'Information whose disclosure could cause serious injury.'],
                ['code' => 'cui', 'label' => 'Controlled Unclassified Information (CUI)', 'level' => 'restricted', 'description' => 'US federal CUI, including export-controlled technical data.'],
                ['code' => 'law_enforcement', 'label' => 'Law Enforcement Data', 'level' => 'restricted', 'description' => 'Investigative, intelligence, or criminal-record information.'],
            ],
        ],
        'technology' => [
            'label' => 'Technology',
            'labels' => [
                ['code' => 'source_code', 'label' => 'Source Code', 'level' => 'confidential', 'description' => 'Proprietary application source and build configuration.'],
                ['code' => 'customer_pii', 'label' => 'Customer PII', 'level' => 'confidential', 'description' => 'Customer account, contact, and usage data.'],
                ['code' => 'telemetry', 'label' => 'Telemetry and Logs', 'level' => 'internal', 'description' => 'Operational logs that may contain identifiers or secrets.'],
            ],
        ],
        'retail' => [
            'label' => 'Retail',
            'labels' => [
                ['code' => 'cardholder_data', 'label' => 'Cardholder Data (PCI)', 'level' => 'restricted', 'description' => 'Primary account numbers and sensitive authentication data.'],
                ['code' => 'customer_pii', 'label' => 'Customer PII', 'level' => 'confidential', 'description' => 'Loyalty, order, and contact data.'],
            ],
        ],
        'energy' => [
            'label' => 'Energy and Utilities',
            'labels' => [
                ['code' => 'ot_data', 'label' => 'Operational Technology Data', 'level' => 'restricted', 'description' => 'SCADA, control-system, and grid configuration data.'],
                ['code' => 'cui', 'label' => 'Controlled Unclassified Information (CUI)', 'level' => 'restricted', 'description' => 'CUI or export-controlled technical data.'],
            ],
        ],
        'manufacturing' => [
            'label' => 'Manufacturing',
            'labels' => [
                ['code' => 'intellectual_property', 'label' => 'Intellectual Property', 'level' => 'confidential', 'description' => 'Designs, processes, and trade secrets.'],
                ['code' => 'ot_data', 'label' => 'Operational Technology Data', 'level' => 'restricted', 'description' => 'Production-line and control-system data.'],
            ],
        ],
        'critical_infrastructure' => [
            'label' => 'Critical Infrastructure',
            'labels' => [
                ['code' => 'ot_data', 'label' => 'Operational Technology Data', 'level' => 'restricted', 'description' => 'Control-system, sensor, and safety data.'],
                ['code' => 'cui', 'label' => 'Controlled Unclassified Information (CUI)', 'level' => 'restricted', 'description' => 'CUI or export-controlled technical data.'],
            ],
        ],
    ],
];
