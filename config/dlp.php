<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data loss prevention
    |--------------------------------------------------------------------------
    |
    | The native scanner is always available. Presidio is optional; point
    | PRESIDIO_URL at a sidecar to add its PII recognisers. A scanner outage
    | falls back to the native patterns rather than silently passing content.
    |
    */

    'presidio_url' => env('PRESIDIO_URL'),
    'presidio_timeout' => (int) env('PRESIDIO_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | AI egress
    |--------------------------------------------------------------------------
    |
    | These types are never sent to an AI provider, regardless of provider or
    | settings. HR, special-category, and credential data are a hard boundary.
    |
    */

    'ai' => [
        'block_types' => ['credential', 'special_category', 'hr'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Uploads are scanned for text-extractable formats. Credentials are blocked;
    | HR and special-category content is flagged for review because a background
    | check or audit artifact may legitimately contain it and must be stored
    | securely rather than rejected.
    |
    */

    'uploads' => [
        'enabled' => env('DLP_UPLOAD_SCAN', true),
        'block_types' => ['credential'],
        'max_scan_bytes' => (int) env('DLP_MAX_SCAN_BYTES', 2097152),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identifier types and false-positive targets
    |--------------------------------------------------------------------------
    |
    | These are the detector outputs the programme measures. The target is a
    | programme target, not a universal industry rate: no standards body
    | publishes a single acceptable false-positive percentage for DLP. The
    | reference records the requirement or benchmark the target is based on.
    |
    | false positive rate = false positives / reviewed findings for the type.
    | A type with fewer reviewed findings than minimum_sample is "insufficient
    | data" rather than "on target".
    |
    */

    'identifier_types' => [
        'aws_access_key' => [
            'label' => 'AWS access key',
            'category' => 'Credential',
            'match_labels' => ['AWS access key'],
            'target_false_positive_rate' => 1.0,
            'minimum_sample' => 10,
            'reference' => 'NIST SP 800-53 Rev. 5 SI-12; internal credential-precision target',
        ],
        'private_key' => [
            'label' => 'Private key',
            'category' => 'Credential',
            'match_labels' => ['Private key'],
            'target_false_positive_rate' => 1.0,
            'minimum_sample' => 10,
            'reference' => 'NIST SP 800-53 Rev. 5 IA-5; internal credential-precision target',
        ],
        'jwt' => [
            'label' => 'JSON Web Token',
            'category' => 'Credential',
            'match_labels' => ['JSON Web Token'],
            'target_false_positive_rate' => 2.0,
            'minimum_sample' => 10,
            'reference' => 'NIST SP 800-53 Rev. 5 IA-5; internal credential-precision target',
        ],
        'secret_assignment' => [
            'label' => 'Secret assignment',
            'category' => 'Credential',
            'match_labels' => ['Secret assignment'],
            'target_false_positive_rate' => 5.0,
            'minimum_sample' => 20,
            'reference' => 'NIST SP 800-53 Rev. 5 SI-12; contextual keyword detector',
        ],
        'credential_generic' => [
            'label' => 'Credential or crypto address',
            'category' => 'Credential',
            'match_labels' => ['Credential or crypto address', 'Credential'],
            'target_false_positive_rate' => 2.0,
            'minimum_sample' => 10,
            'reference' => 'NIST SP 800-53 Rev. 5 IA-5; internal credential-precision target',
        ],
        'payment_card' => [
            'label' => 'Payment card number',
            'category' => 'Financial',
            'match_labels' => ['Payment card number'],
            'target_false_positive_rate' => 2.0,
            'minimum_sample' => 20,
            'reference' => 'PCI DSS v4.0.1 requirements 3.4 and 12.5.2; internal tuning target',
        ],
        'payment_bank' => [
            'label' => 'Payment or bank account number',
            'category' => 'Financial',
            'match_labels' => ['Payment or bank account number'],
            'target_false_positive_rate' => 3.0,
            'minimum_sample' => 20,
            'reference' => 'PCI DSS v4.0.1 requirement 3.4; internal tuning target',
        ],
        'ssn' => [
            'label' => 'US social security number',
            'category' => 'Government identifier',
            'match_labels' => ['US social security number'],
            'target_false_positive_rate' => 2.0,
            'minimum_sample' => 20,
            'reference' => 'NIST SP 800-122; internal precision target',
        ],
        'sin' => [
            'label' => 'Social insurance number',
            'category' => 'Government identifier',
            'match_labels' => ['Social insurance number'],
            'target_false_positive_rate' => 2.0,
            'minimum_sample' => 20,
            'reference' => 'PIPEDA and Canadian privacy practice; internal precision target',
        ],
        'government_identifier' => [
            'label' => 'Government or medical identifier',
            'category' => 'Government identifier',
            'match_labels' => ['Government or medical identifier'],
            'target_false_positive_rate' => 5.0,
            'minimum_sample' => 20,
            'reference' => 'NIST SP 800-122; Presidio entity mapping',
        ],
        'health_medical' => [
            'label' => 'Health or medical information',
            'category' => 'Special category',
            'match_labels' => ['Health or medical information'],
            'target_false_positive_rate' => 10.0,
            'minimum_sample' => 20,
            'reference' => 'HIPAA Security Rule; contextual term detector',
        ],
        'criminal_background' => [
            'label' => 'Criminal or background-check data',
            'category' => 'Special category',
            'match_labels' => ['Criminal or background-check data'],
            'target_false_positive_rate' => 10.0,
            'minimum_sample' => 20,
            'reference' => 'GDPR Article 10; contextual term detector',
        ],
        'biometric' => [
            'label' => 'Biometric information',
            'category' => 'Special category',
            'match_labels' => ['Biometric information'],
            'target_false_positive_rate' => 10.0,
            'minimum_sample' => 20,
            'reference' => 'GDPR Article 9; contextual term detector',
        ],
        'protected_characteristic' => [
            'label' => 'Protected characteristic',
            'category' => 'Special category',
            'match_labels' => ['Protected characteristic'],
            'target_false_positive_rate' => 10.0,
            'minimum_sample' => 20,
            'reference' => 'GDPR Article 9; contextual term detector',
        ],
        'special_category_generic' => [
            'label' => 'Special-category data',
            'category' => 'Special category',
            'match_labels' => ['Special-category data'],
            'target_false_positive_rate' => 5.0,
            'minimum_sample' => 20,
            'reference' => 'GDPR Article 9; Presidio entity mapping',
        ],
        'hr_personnel' => [
            'label' => 'HR or personnel data',
            'category' => 'HR',
            'match_labels' => ['HR or personnel data'],
            'target_false_positive_rate' => 15.0,
            'minimum_sample' => 20,
            'reference' => 'Internal HR data boundary; contextual term detector',
        ],
        'email_address' => [
            'label' => 'Email address',
            'category' => 'Personal identifier',
            'match_labels' => ['Email address'],
            'target_false_positive_rate' => 15.0,
            'minimum_sample' => 20,
            'reference' => 'NIST SP 800-122; contextual identifier',
        ],
        'phone_number' => [
            'label' => 'Phone number',
            'category' => 'Personal identifier',
            'match_labels' => ['Phone number'],
            'target_false_positive_rate' => 15.0,
            'minimum_sample' => 20,
            'reference' => 'NIST SP 800-122; contextual identifier',
        ],
        'personal_identifier' => [
            'label' => 'Personal identifier',
            'category' => 'Personal identifier',
            'match_labels' => ['Personal identifier'],
            'target_false_positive_rate' => 15.0,
            'minimum_sample' => 20,
            'reference' => 'NIST SP 800-122; Presidio entity mapping',
        ],
    ],

];
