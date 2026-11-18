<?php

return [
    'navigation' => [
        'label' => 'Controls',
        'group' => 'Foundations',
    ],
    'model' => [
        'label' => 'Control',
        'plural_label' => 'Controls',
    ],
    'breadcrumb' => [
        'title' => 'Controls',
    ],
    'form' => [
        'code' => [
            'tooltip' => 'Enter a unique code for this control. This code will be used to identify this control in the system.',
        ],
        'standard' => [
            'label' => 'Standard',
            'tooltip' => 'All controls must belong to a standard. If you dont have a standard to relate this control to, consider creating a new one first.',
        ],
        'enforcement' => [
            'tooltip' => 'Select an enforcement category for this control. This will help determine how this control is enforced.',
        ],
        'type' => [
            'label' => 'Type',
            'tooltip' => 'Select the type of control (e.g., Preventive, Detective, Corrective). This categorizes the control based on when it acts in relation to a security incident.',
        ],
        'category' => [
            'label' => 'Category',
            'tooltip' => 'Select the category of control (e.g., Technical, Administrative, Physical). This categorizes the control based on its implementation approach.',
        ],
        'title' => [
            'tooltip' => 'Enter a title for this control.',
        ],
        'description' => [
            'tooltip' => 'Enter a description for this control. This should describe, in detail, the requirements for this control.',
        ],
        'discussion' => [
            'tooltip' => 'Optional: Provide any context or additional information about this control that would help someone determine how to implement it.',
        ],
        'canonical_outcomes' => [
            'label' => 'Canonical outcomes',
            'helper' => 'Which outcomes does this control satisfy? Links are reviewed by a person and never matched automatically. Leave this empty when the requirement does not clearly serve an outcome: an unlinked control is reported as a coverage gap, not treated as a failure.',
            'policy_helper' => 'Which outcomes does this policy or document support? These links are reviewed by a person and never matched automatically. A policy can support an outcome directly, or through the controls it is attached to. Leaving it empty is a coverage gap to review, not a failure.',
        ],
        'test' => [
            'label' => 'Test Plan',
            'tooltip' => 'Optional: How do you plan to test that this control is in place and effective?',
        ],
    ],
    'table' => [
        'description' => 'Controls are the \'how\' of security implementation - they are the specific mechanisms, policies, procedures, and tools used to enforce standards and protect assets. Controls can be technical (like firewalls or encryption), administrative (like policies or training), or physical (like security cameras or door locks). Each control should be designed to address specific risks and meet particular security requirements defined by standards. For instance, to meet a standard requiring secure data transmission, a control might specify the use of TLS 1.2 or higher for all external communications. Controls are the practical manifestation of security standards and form the backbone of an organization\'s security infrastructure.',
        'empty_state' => [
            'heading' => 'No controls found',
            'description' => 'Get started by importing a standard bundle or creating a new control.',
        ],
        'columns' => [
            'code' => 'Code',
            'title' => 'Title',
            'standard' => 'Standard',
            'canonical_outcomes' => 'Canonical outcome',
            'canonical_outcomes_unlinked' => 'Not linked',
            'type' => 'Type',
            'category' => 'Category',
            'enforcement' => 'Enforcement',
            'effectiveness' => 'Effectiveness',
            'applicability' => 'Applicability',
            'assessed' => 'Last Assessed',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'filters' => [
            'standard' => 'Standard',
            'canonical_link' => 'Canonical outcome link',
            'canonical_linked' => 'Linked',
            'canonical_unlinked' => 'Not linked',
            'effectiveness' => 'Effectiveness',
            'type' => 'Type',
            'category' => 'Category',
            'enforcement' => 'Enforcement',
            'applicability' => 'Applicability',
        ],
    ],
    'infolist' => [
        'section_title' => 'Control Details',
        'canonical_outcomes' => 'Canonical outcomes',
        'canonical_outcomes_empty' => 'Not linked — no reviewed match. This is a coverage gap, not a failure.',
        'canonical_outcomes_policy_empty' => 'Not linked — no reviewed match. This policy is a coverage gap until someone reviews it, not a failure.',
        'test_plan' => 'Test Plan',
    ],
];
