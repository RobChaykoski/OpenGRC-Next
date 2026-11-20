<?php

return [
    'navigation' => 'Organization scope',
    'navigation_group' => 'Foundations',
    'title' => 'Framework and control scoping',

    'intro' => [
        'title' => 'Choose the organization profile, see what applies',
        'body' => 'The scoping engine matches industry, region, and data classifications to the framework catalogue. It recommends what to import and which controls and documents to start with; it does not import a framework or change the register.',
    ],

    'sections' => [
        'organization' => 'Organization profile',
        'data' => 'Data classifications',
        'data_help' => 'Select the sensitive data the organization actually handles. Privacy and payment frameworks depend on this, not on industry alone.',
    ],

    'form' => [
        'name' => 'Organization name',
        'name_placeholder' => 'Example Hospital',
        'industry' => 'Industry',
        'country' => 'Country or region',
        'province' => 'Province or state',
        'province_placeholder' => 'Select a subdivision',
        'employees' => 'Employees',
        'revenue' => 'Revenue band',
        'data_classifications' => 'Sensitive data handled',
        'assess' => 'Assess profile',
    ],

    'industries' => [
        'healthcare' => 'Healthcare',
        'financial_services' => 'Financial services',
        'technology' => 'Technology / SaaS',
        'government' => 'Government / public sector',
        'critical_infrastructure' => 'Critical infrastructure',
        'education' => 'Education',
        'energy' => 'Energy / utilities',
        'manufacturing' => 'Manufacturing',
        'retail' => 'Retail / e-commerce',
        'other' => 'Other',
    ],

    'countries' => [
        'CA' => 'Canada',
        'US' => 'United States',
        'EU' => 'European Union',
        'UK' => 'United Kingdom',
        'AU' => 'Australia',
        'other' => 'Other',
    ],

    'subdivisions' => [
        'CA-ON' => 'Ontario',
        'CA-QC' => 'Quebec',
        'CA-BC' => 'British Columbia',
        'CA-AB' => 'Alberta',
        'CA-MB' => 'Manitoba',
        'CA-SK' => 'Saskatchewan',
        'CA-NS' => 'Nova Scotia',
        'CA-NB' => 'New Brunswick',
        'CA-NL' => 'Newfoundland and Labrador',
        'CA-PE' => 'Prince Edward Island',
        'US-NY' => 'New York',
        'US-CA' => 'California',
        'US-TX' => 'Texas',
        'US-WA' => 'Washington',
        'US-FL' => 'Florida',
        'US-IL' => 'Illinois',
    ],

    'employees' => [
        'small' => '1–49',
        'medium' => '50–249',
        'large' => '250–999',
        'enterprise' => '1,000+',
    ],

    'revenue' => [
        'under_10m' => 'Under 10M',
        '10m_50m' => '10M–50M',
        '50m_250m' => '50M–250M',
        '250m_plus' => '250M+',
    ],

    'results' => [
        'heading' => 'Assessment',
        'frameworks' => 'Recommended frameworks',
        'controls' => 'Controls that apply',
        'documents' => 'Documents to start',
        'gaps' => 'Gaps and caveats',
        'no_gaps' => 'No profile gaps were reported. That does not mean every control is implemented; it means the scoping inputs are complete.',
        'framework' => 'Framework',
        'category' => 'Category',
        'priority' => 'Priority',
        'confidence' => 'Confidence',
        'why' => 'Why it applies',
        'code' => 'Code',
        'control' => 'Control',
        'used_by' => 'Used by',
        'document' => 'Document',
        'frequency' => 'Review',
        'empty' => 'Choose an industry, region, and at least one sensitive data type, then assess the profile.',
        'note_title' => 'What this is, and what it is not',
        'note_body' => 'This is a scoping recommendation based on the catalogue in config/framework_scoping.php. It is not a certification, a legal opinion, or an import. Import the framework, link controls to outcomes, and record the scope decision.',
        'import_hint' => 'Import a framework with php artisan opengrc:framework-import <code>.',
        'open_matrix' => 'Open the assurance matrix',
        'assess_again' => 'Change the profile and assess again',
    ],

    'priority' => [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],

    'confidence' => [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],
];
