<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unified control catalogue
    |--------------------------------------------------------------------------
    |
    | One entry per outcome, not per framework. Frameworks point at these codes,
    | which is what lets the scoping report deduplicate overlapping controls.
    |
    */
    'controls' => [
        'CTRL-GOV-001' => ['name' => 'Governance and policy framework', 'category' => 'Governance', 'risk_domain' => 'Governance', 'priority' => 1, 'evidence' => 'Approved policy set, governance minutes', 'sources' => ['OpenGRC Policies'], 'automation' => 'low', 'frequency' => 'annual'],
        'CTRL-RISK-001' => ['name' => 'Risk assessment and treatment', 'category' => 'Risk', 'risk_domain' => 'Risk', 'priority' => 1, 'evidence' => 'Risk register, treatment plans, residual approvals', 'sources' => ['OpenGRC Risk Register'], 'automation' => 'medium', 'frequency' => 'quarterly'],
        'CTRL-ASSET-001' => ['name' => 'Asset and application inventory', 'category' => 'Asset Management', 'risk_domain' => 'Asset Management', 'priority' => 2, 'evidence' => 'Inventory export with owners and criticality', 'sources' => ['PatchMon', 'Intune', 'OpenGRC Assets'], 'automation' => 'high', 'frequency' => 'weekly'],
        'CTRL-ACCESS-001' => ['name' => 'Least-privilege access control', 'category' => 'Access Control', 'risk_domain' => 'Identity', 'priority' => 1, 'evidence' => 'Role model, access matrix, privileged account list', 'sources' => ['Microsoft Entra ID', 'OpenGRC Users'], 'automation' => 'medium', 'frequency' => 'quarterly'],
        'CTRL-ACCESS-002' => ['name' => 'Multi-factor authentication', 'category' => 'Identity', 'risk_domain' => 'Identity', 'priority' => 1, 'evidence' => 'MFA coverage report', 'sources' => ['Microsoft Entra ID', 'Okta'], 'automation' => 'high', 'frequency' => 'weekly'],
        'CTRL-ACCESS-003' => ['name' => 'Access reviews and dormancy', 'category' => 'Access Control', 'risk_domain' => 'Identity', 'priority' => 2, 'evidence' => 'Quarterly review sign-off, dormant account report', 'sources' => ['Microsoft Entra ID'], 'automation' => 'high', 'frequency' => 'quarterly'],
        'CTRL-VULN-001' => ['name' => 'Technical vulnerability and patch management', 'category' => 'Vulnerability Management', 'risk_domain' => 'Vulnerability Management', 'priority' => 1, 'evidence' => 'Patch compliance, scan results, remediation tickets', 'sources' => ['PatchMon', 'Nessus', 'OpenVAS', 'Defender'], 'automation' => 'high', 'frequency' => 'weekly'],
        'CTRL-LOG-001' => ['name' => 'Logging, monitoring, and detection', 'category' => 'Monitoring', 'risk_domain' => 'Security Operations', 'priority' => 1, 'evidence' => 'Log source inventory, alert samples, retention configuration', 'sources' => ['Wazuh', 'Defender', 'OpenGRC Audit Log'], 'automation' => 'medium', 'frequency' => 'monthly'],
        'CTRL-IR-001' => ['name' => 'Incident response and breach handling', 'category' => 'Security Operations', 'risk_domain' => 'Security Operations', 'priority' => 1, 'evidence' => 'Incident response plan, exercise report, breach register', 'sources' => ['OpenGRC Incidents', 'ServiceNow'], 'automation' => 'low', 'frequency' => 'annual'],
        'CTRL-VENDOR-001' => ['name' => 'Third-party and vendor risk management', 'category' => 'Vendor Management', 'risk_domain' => 'Vendor Management', 'priority' => 2, 'evidence' => 'Vendor inventory, assessments, contract clauses', 'sources' => ['OpenGRC Vendors', 'OpenGRC Surveys'], 'automation' => 'medium', 'frequency' => 'annual'],
        'CTRL-BCP-001' => ['name' => 'Business continuity and recovery', 'category' => 'Business Continuity', 'risk_domain' => 'Business Continuity', 'priority' => 2, 'evidence' => 'BCP/DR plan, restore test, RTO exercise', 'sources' => ['OpenGRC BCP'], 'automation' => 'low', 'frequency' => 'annual'],
        'CTRL-PRIV-001' => ['name' => 'Privacy notice and consent', 'category' => 'Privacy', 'risk_domain' => 'Privacy', 'priority' => 1, 'evidence' => 'Privacy notice, consent records, PIAs', 'sources' => ['OpenGRC Policies', 'OpenGRC Trust Center'], 'automation' => 'low', 'frequency' => 'annual'],
        'CTRL-PRIV-002' => ['name' => 'Data subject access and correction', 'category' => 'Privacy', 'risk_domain' => 'Privacy', 'priority' => 2, 'evidence' => 'Request log, response times, redaction records', 'sources' => ['OpenGRC Data Requests'], 'automation' => 'medium', 'frequency' => 'quarterly'],
        'CTRL-PRIV-003' => ['name' => 'Breach reporting and notification', 'category' => 'Privacy', 'risk_domain' => 'Privacy', 'priority' => 1, 'evidence' => 'Breach register, notification records, regulator correspondence', 'sources' => ['OpenGRC Incidents'], 'automation' => 'low', 'frequency' => 'as needed'],
        'CTRL-CRYPTO-001' => ['name' => 'Encryption and key management', 'category' => 'Cloud Security', 'risk_domain' => 'Data Protection', 'priority' => 2, 'evidence' => 'Encryption configuration, key rotation record', 'sources' => ['AWS', 'Azure', 'OpenGRC Storage Settings'], 'automation' => 'medium', 'frequency' => 'quarterly'],
        'CTRL-TRAIN-001' => ['name' => 'Security and privacy awareness', 'category' => 'Governance', 'risk_domain' => 'People', 'priority' => 3, 'evidence' => 'Training completion, phishing simulation results', 'sources' => ['OpenGRC Training', 'HR System'], 'automation' => 'medium', 'frequency' => 'annual'],
        'CTRL-SDLC-001' => ['name' => 'Secure development and change control', 'category' => 'Application Security', 'risk_domain' => 'Application Security', 'priority' => 2, 'evidence' => 'CI gates, code review records, dependency scans', 'sources' => ['GitHub', 'OpenGRC Migrations'], 'automation' => 'high', 'frequency' => 'continuous'],
        'CTRL-OT-001' => ['name' => 'Operational technology protection', 'category' => 'Monitoring', 'risk_domain' => 'Operational Technology', 'priority' => 1, 'evidence' => 'Network segmentation, OT asset inventory, change logs', 'sources' => ['Nozomi', 'Claroty', 'OpenGRC Assets'], 'automation' => 'medium', 'frequency' => 'monthly'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document register
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'Information Security Policy' => ['frequency' => 'annual'],
        'Acceptable Use Policy' => ['frequency' => 'annual'],
        'Asset Management Policy' => ['frequency' => 'annual'],
        'Incident Response Plan' => ['frequency' => 'annual'],
        'Vulnerability Management Standard' => ['frequency' => 'annual'],
        'Risk Management Procedure' => ['frequency' => 'annual'],
        'Business Continuity Plan' => ['frequency' => 'annual'],
        'Disaster Recovery Plan' => ['frequency' => 'annual'],
        'Privacy Policy' => ['frequency' => 'annual'],
        'Data Classification Standard' => ['frequency' => 'annual'],
        'Supplier Risk Procedure' => ['frequency' => 'annual'],
        'Access Control Standard' => ['frequency' => 'annual'],
        'Secure Development Standard' => ['frequency' => 'annual'],
        'OT Security Policy' => ['frequency' => 'annual'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Frameworks
    |--------------------------------------------------------------------------
    |
    | `regions` / `industries` / `labels` empty means universal. A framework is
    | applicable when every non-empty condition matches the profile.
    |
    */
    'frameworks' => [
        'nist_csf' => [
            'name' => 'NIST CSF 2.0', 'category' => 'Cybersecurity', 'priority' => 1,
            'regions' => [], 'industries' => [], 'labels' => [],
            'rationale' => 'A governance-first baseline that maps cleanly to other frameworks.',
            'drivers' => ['Business'], 'documents' => ['Information Security Policy', 'Risk Management Procedure'],
            'controls' => ['CTRL-GOV-001', 'CTRL-RISK-001', 'CTRL-ASSET-001', 'CTRL-ACCESS-001', 'CTRL-VULN-001', 'CTRL-LOG-001', 'CTRL-IR-001', 'CTRL-VENDOR-001', 'CTRL-BCP-001'],
        ],
        'nist_800_53' => [
            'name' => 'NIST SP 800-53 Rev. 5', 'category' => 'Cybersecurity', 'priority' => 2,
            'regions' => [], 'industries' => ['government', 'critical_infrastructure', 'technology'], 'labels' => [],
            'rationale' => 'A control catalogue for organisations that need a broad, testable control set.',
            'drivers' => ['Regulatory', 'Contractual'], 'documents' => ['Information Security Policy', 'Access Control Standard', 'Incident Response Plan'],
            'controls' => ['CTRL-GOV-001', 'CTRL-RISK-001', 'CTRL-ASSET-001', 'CTRL-ACCESS-001', 'CTRL-ACCESS-002', 'CTRL-ACCESS-003', 'CTRL-VULN-001', 'CTRL-LOG-001', 'CTRL-IR-001', 'CTRL-CRYPTO-001'],
        ],
        'cis_v8' => [
            'name' => 'CIS Critical Security Controls v8', 'category' => 'Cybersecurity', 'priority' => 1,
            'regions' => [], 'industries' => [], 'labels' => [],
            'rationale' => 'Prioritised, implementation-grouped controls with strong technical evidence.',
            'drivers' => ['Business'], 'documents' => ['Asset Management Policy', 'Vulnerability Management Standard', 'Access Control Standard'],
            'controls' => ['CTRL-ASSET-001', 'CTRL-ACCESS-001', 'CTRL-ACCESS-002', 'CTRL-VULN-001', 'CTRL-LOG-001', 'CTRL-IR-001', 'CTRL-TRAIN-001'],
        ],
        'iso_27001' => [
            'name' => 'ISO/IEC 27001:2022', 'category' => 'Cybersecurity', 'priority' => 1,
            'regions' => [], 'industries' => [], 'labels' => [],
            'rationale' => 'The most recognised certifiable management system; useful when customers ask for certification.',
            'drivers' => ['Contractual', 'Business'], 'documents' => ['Information Security Policy', 'Risk Management Procedure', 'Supplier Risk Procedure'],
            'controls' => ['CTRL-GOV-001', 'CTRL-RISK-001', 'CTRL-ASSET-001', 'CTRL-ACCESS-001', 'CTRL-VULN-001', 'CTRL-LOG-001', 'CTRL-IR-001', 'CTRL-VENDOR-001', 'CTRL-BCP-001', 'CTRL-CRYPTO-001'],
        ],
        'soc2' => [
            'name' => 'SOC 2 (TSC 2017)', 'category' => 'Assurance', 'priority' => 1,
            'regions' => [], 'industries' => ['technology', 'financial_services', 'healthcare'], 'labels' => [],
            'rationale' => 'Customer-facing assurance report for service organisations handling their data.',
            'drivers' => ['Contractual'], 'documents' => ['Information Security Policy', 'Incident Response Plan', 'Supplier Risk Procedure', 'Privacy Policy'],
            'controls' => ['CTRL-GOV-001', 'CTRL-RISK-001', 'CTRL-ACCESS-001', 'CTRL-ACCESS-002', 'CTRL-ACCESS-003', 'CTRL-VULN-001', 'CTRL-LOG-001', 'CTRL-IR-001', 'CTRL-VENDOR-001', 'CTRL-BCP-001', 'CTRL-PRIV-001'],
        ],
        'cmmc' => [
            'name' => 'CMMC 2.0 Level 2', 'category' => 'Defence', 'priority' => 1,
            'regions' => ['US'], 'industries' => [], 'labels' => ['cui'],
            'rationale' => 'Required when handling US controlled unclassified information in the defence supply chain.',
            'drivers' => ['Regulatory', 'Contractual'], 'documents' => ['Information Security Policy', 'Incident Response Plan', 'Access Control Standard'],
            'controls' => ['CTRL-GOV-001', 'CTRL-RISK-001', 'CTRL-ASSET-001', 'CTRL-ACCESS-001', 'CTRL-ACCESS-002', 'CTRL-VULN-001', 'CTRL-LOG-001', 'CTRL-IR-001', 'CTRL-TRAIN-001', 'CTRL-CRYPTO-001'],
        ],
        'pipeda' => [
            'name' => 'PIPEDA', 'category' => 'Privacy', 'priority' => 1,
            'regions' => ['CA'], 'industries' => [], 'labels' => ['personal_information', 'pii', 'customer_pii', 'phi', 'employee_pii'],
            'rationale' => 'Federal private-sector privacy law for Canadian commercial activity.',
            'drivers' => ['Regulatory'], 'documents' => ['Privacy Policy', 'Data Classification Standard', 'Incident Response Plan'],
            'controls' => ['CTRL-PRIV-001', 'CTRL-PRIV-002', 'CTRL-PRIV-003', 'CTRL-ACCESS-003', 'CTRL-VENDOR-001', 'CTRL-TRAIN-001'],
        ],
        'law25' => [
            'name' => 'Quebec Law 25', 'category' => 'Privacy', 'priority' => 1,
            'regions' => ['CA-QC'], 'industries' => [], 'labels' => ['personal_information', 'pii', 'customer_pii', 'phi'],
            'rationale' => 'Quebec-specific consent, transparency, privacy-officer and breach obligations.',
            'drivers' => ['Regulatory'], 'documents' => ['Privacy Policy', 'Data Classification Standard', 'Incident Response Plan'],
            'controls' => ['CTRL-PRIV-001', 'CTRL-PRIV-002', 'CTRL-PRIV-003', 'CTRL-TRAIN-001'],
        ],
        'phipa' => [
            'name' => 'PHIPA (Ontario)', 'category' => 'Privacy', 'priority' => 1,
            'regions' => ['CA-ON'], 'industries' => ['healthcare'], 'labels' => ['phi', 'clinical_records', 'health_research'],
            'rationale' => 'Health information custodian obligations, including circle of care and breach reporting.',
            'drivers' => ['Regulatory'], 'documents' => ['Privacy Policy', 'Data Classification Standard', 'Incident Response Plan'],
            'controls' => ['CTRL-PRIV-001', 'CTRL-PRIV-002', 'CTRL-PRIV-003', 'CTRL-ACCESS-001', 'CTRL-VENDOR-001', 'CTRL-TRAIN-001'],
        ],
        'hipaa' => [
            'name' => 'HIPAA Security Rule', 'category' => 'Privacy', 'priority' => 1,
            'regions' => ['US'], 'industries' => ['healthcare'], 'labels' => ['phi'],
            'rationale' => 'Applies when handling US PHI as a covered entity or business associate.',
            'drivers' => ['Regulatory', 'Contractual'], 'documents' => ['Privacy Policy', 'Incident Response Plan', 'Access Control Standard'],
            'controls' => ['CTRL-PRIV-001', 'CTRL-PRIV-002', 'CTRL-PRIV-003', 'CTRL-ACCESS-001', 'CTRL-LOG-001', 'CTRL-VENDOR-001', 'CTRL-TRAIN-001'],
        ],
        'gdpr' => [
            'name' => 'GDPR', 'category' => 'Privacy', 'priority' => 1,
            'regions' => ['EU', 'UK'], 'industries' => [], 'labels' => ['personal_information', 'pii', 'customer_pii', 'phi'],
            'rationale' => 'Applies to EU/UK data subjects regardless of where the organisation is established.',
            'drivers' => ['Regulatory'], 'documents' => ['Privacy Policy', 'Data Classification Standard', 'Incident Response Plan'],
            'controls' => ['CTRL-PRIV-001', 'CTRL-PRIV-002', 'CTRL-PRIV-003', 'CTRL-ACCESS-003', 'CTRL-VENDOR-001', 'CTRL-TRAIN-001'],
        ],
        'pci_dss' => [
            'name' => 'PCI DSS', 'category' => 'Payment', 'priority' => 1,
            'regions' => [], 'industries' => [], 'labels' => ['cardholder_data'],
            'rationale' => 'Required for any system that stores, processes, or transmits cardholder data.',
            'drivers' => ['Contractual', 'Regulatory'], 'documents' => ['Information Security Policy', 'Access Control Standard', 'Vulnerability Management Standard'],
            'controls' => ['CTRL-ACCESS-001', 'CTRL-ACCESS-002', 'CTRL-VULN-001', 'CTRL-LOG-001', 'CTRL-CRYPTO-001', 'CTRL-SDLC-001'],
        ],
        'ferpa' => [
            'name' => 'FERPA', 'category' => 'Privacy', 'priority' => 1,
            'regions' => ['US'], 'industries' => ['education'], 'labels' => ['student_records'],
            'rationale' => 'Protects US education records and limits disclosure without consent.',
            'drivers' => ['Regulatory'], 'documents' => ['Privacy Policy', 'Data Classification Standard'],
            'controls' => ['CTRL-PRIV-001', 'CTRL-PRIV-002', 'CTRL-ACCESS-001', 'CTRL-VENDOR-001'],
        ],
        'pcmltfa' => [
            'name' => 'PCMLTFA', 'category' => 'Financial Crime', 'priority' => 1,
            'regions' => ['CA'], 'industries' => ['financial_services'], 'labels' => ['kyc_records', 'financial_records'],
            'rationale' => 'AML/KYC program, beneficial ownership, and record-retention obligations.',
            'drivers' => ['Regulatory'], 'documents' => ['Risk Management Procedure', 'Supplier Risk Procedure', 'Privacy Policy'],
            'controls' => ['CTRL-RISK-001', 'CTRL-PRIV-002', 'CTRL-VENDOR-001', 'CTRL-LOG-001', 'CTRL-TRAIN-001'],
        ],
        'osfi' => [
            'name' => 'OSFI guidelines', 'category' => 'Financial Services', 'priority' => 1,
            'regions' => ['CA'], 'industries' => ['financial_services'], 'labels' => ['financial_records', 'kyc_records'],
            'rationale' => 'Governance, cyber-resilience, and third-party risk expectations for federally regulated institutions.',
            'drivers' => ['Regulatory'], 'documents' => ['Information Security Policy', 'Business Continuity Plan', 'Supplier Risk Procedure', 'Incident Response Plan'],
            'controls' => ['CTRL-GOV-001', 'CTRL-RISK-001', 'CTRL-VENDOR-001', 'CTRL-BCP-001', 'CTRL-IR-001', 'CTRL-LOG-001'],
        ],
        'iso_22301' => [
            'name' => 'ISO 22301', 'category' => 'Business Continuity', 'priority' => 2,
            'regions' => [], 'industries' => ['financial_services', 'healthcare', 'critical_infrastructure', 'government'], 'labels' => [],
            'rationale' => 'Business continuity management where service interruption has regulatory or safety impact.',
            'drivers' => ['Business', 'Regulatory'], 'documents' => ['Business Continuity Plan', 'Disaster Recovery Plan'],
            'controls' => ['CTRL-RISK-001', 'CTRL-BCP-001', 'CTRL-VENDOR-001'],
        ],
        'iec_62443' => [
            'name' => 'IEC 62443', 'category' => 'Operational Technology', 'priority' => 1,
            'regions' => [], 'industries' => ['energy', 'manufacturing', 'critical_infrastructure'], 'labels' => ['ot_data'],
            'rationale' => 'OT/ICS security program for industrial control environments.',
            'drivers' => ['Regulatory', 'Safety'], 'documents' => ['OT Security Policy', 'Asset Management Policy', 'Incident Response Plan'],
            'controls' => ['CTRL-ASSET-001', 'CTRL-ACCESS-001', 'CTRL-OT-001', 'CTRL-LOG-001', 'CTRL-IR-001', 'CTRL-BCP-001'],
        ],
        'nerc_cip' => [
            'name' => 'NERC CIP', 'category' => 'Operational Technology', 'priority' => 1,
            'regions' => ['US'], 'industries' => ['energy'], 'labels' => ['ot_data'],
            'rationale' => 'Mandatory reliability standards for the North American bulk electric system.',
            'drivers' => ['Regulatory'], 'documents' => ['OT Security Policy', 'Access Control Standard', 'Incident Response Plan'],
            'controls' => ['CTRL-ASSET-001', 'CTRL-ACCESS-001', 'CTRL-ACCESS-003', 'CTRL-OT-001', 'CTRL-LOG-001', 'CTRL-IR-001', 'CTRL-TRAIN-001'],
        ],
        'hitrust' => [
            'name' => 'HITRUST CSF', 'category' => 'Healthcare', 'priority' => 2,
            'regions' => [], 'industries' => ['healthcare'], 'labels' => ['phi'],
            'rationale' => 'Certification that harmonises HIPAA and other healthcare requirements.',
            'drivers' => ['Contractual'], 'documents' => ['Information Security Policy', 'Risk Management Procedure', 'Privacy Policy'],
            'controls' => ['CTRL-GOV-001', 'CTRL-RISK-001', 'CTRL-ACCESS-001', 'CTRL-VULN-001', 'CTRL-LOG-001', 'CTRL-PRIV-001', 'CTRL-VENDOR-001'],
        ],
        'iso_31000' => [
            'name' => 'ISO 31000', 'category' => 'Risk', 'priority' => 2,
            'regions' => [], 'industries' => [], 'labels' => [],
            'rationale' => 'Risk management principles to structure the risk register and treatment decisions.',
            'drivers' => ['Business'], 'documents' => ['Risk Management Procedure'],
            'controls' => ['CTRL-GOV-001', 'CTRL-RISK-001'],
        ],
    ],

    'roadmap' => [
        ['phase' => 1, 'name' => 'Documentation foundation', 'focus' => 'Policies and standards'],
        ['phase' => 2, 'name' => 'Risk management', 'focus' => 'Register, treatment, appetite'],
        ['phase' => 3, 'name' => 'Control implementation', 'focus' => 'Owners, procedures, mappings'],
        ['phase' => 4, 'name' => 'Evidence collection', 'focus' => 'Evidence ledger and retention'],
        ['phase' => 5, 'name' => 'Continuous control monitoring', 'focus' => 'Connectors and validation rules'],
        ['phase' => 6, 'name' => 'Audit automation', 'focus' => 'Audit items, findings, remediation'],
    ],
];
