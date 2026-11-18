<?php

use App\Services\Frameworks\OscalCsvImporter;

/**
 * Framework provenance and licensing register.
 *
 * Every control library shipped with OpenGRC is listed here: where it came from,
 * what its licence allows, and how many controls to expect. `opengrc:framework-audit`
 * validates imported data against this file, so provenance and content cannot
 * drift apart silently.
 *
 * See docs/grc/framework-catalog.md for the human-readable version.
 */
return [

    'standards' => [

        'CSCv8' => [
            'name' => 'CIS Critical Security Controls v8',
            'authority' => 'Center for Internet Security',
            'licence' => 'CIS Terms of Use',
            'redistributable' => 'conditional',
            'provenance' => 'https://www.cisecurity.org/controls/v8',
            'data' => 'resources/data/csc8.csv',
            'seeder' => 'CSCSeeder',
            'expected_controls' => 18,
            'note' => 'Free to use with attribution; check CIS terms before redistributing the control text in a commercial product.',
        ],

        'CIS-CSC-IG1' => [
            'name' => 'CIS Critical Security Controls v8 - Implementation Group 1',
            'authority' => 'Center for Internet Security',
            'licence' => 'CIS Terms of Use',
            'redistributable' => 'conditional',
            'provenance' => 'https://www.cisecurity.org/controls/v8',
            'data' => 'resources/data/CIS-CSC-All.csv',
            'seeder' => 'CISCSCSeeder',
            'expected_controls' => null,
            'note' => 'Derived from the full control set; counts vary with the CIS mapping revision.',
        ],

        'CIS-CSC-IG2' => [
            'name' => 'CIS Critical Security Controls v8 - Implementation Group 2',
            'authority' => 'Center for Internet Security',
            'licence' => 'CIS Terms of Use',
            'redistributable' => 'conditional',
            'provenance' => 'https://www.cisecurity.org/controls/v8',
            'data' => 'resources/data/CIS-CSC-All.csv',
            'seeder' => 'CISCSCSeeder',
            'expected_controls' => null,
            'note' => 'Superset of IG1.',
        ],

        'CIS-CSC-IG3' => [
            'name' => 'CIS Critical Security Controls v8 - Implementation Group 3',
            'authority' => 'Center for Internet Security',
            'licence' => 'CIS Terms of Use',
            'redistributable' => 'conditional',
            'provenance' => 'https://www.cisecurity.org/controls/v8',
            'data' => 'resources/data/CIS-CSC-All.csv',
            'seeder' => 'CISCSCSeeder',
            'expected_controls' => null,
            'note' => 'Superset of IG2.',
        ],

        '800-53 (Low)' => [
            'name' => 'NIST SP 800-53 Rev. 5 - Low Baseline (legacy bundle)',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final',
            'data' => 'resources/data/sp80053Low.csv',
            'seeder' => 'SP80053LowSeeder',
            'expected_controls' => 186,
            'note' => 'Legacy bundle retained for existing programmes. Use NIST-800-53B-Low for a fresh import; it is generated from the official OSCAL baseline and matches the published count.',
        ],

        'NIST-800-53r5' => [
            'name' => 'NIST SP 800-53 Rev. 5 - Full Control Catalogue',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final',
            'data' => 'resources/data/nist-sp800-53r5.csv',
            'importer' => OscalCsvImporter::class,
            'reference_url' => 'https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final',
            'description' => 'The complete NIST SP 800-53 Rev. 5.2.0 security and privacy control catalogue, including control enhancements. Withdrawn controls are omitted. Generated from the NIST OSCAL catalog; see scripts/oscal-to-csv.php.',
            'expected_controls' => 1014,
            'note' => 'Public domain in the United States. NIST requests attribution and does not endorse OpenGRC. Some NIST publications quote third-party material that is excluded from this bundle.',
        ],

        'NIST-800-53B-Low' => [
            'name' => 'NIST SP 800-53B - Low Baseline',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/53b/final',
            'data' => 'resources/data/nist-sp800-53b-low.csv',
            'importer' => OscalCsvImporter::class,
            'reference_url' => 'https://csrc.nist.gov/pubs/sp/800/53b/final',
            'description' => 'The NIST SP 800-53B low-impact baseline, generated from the official resolved OSCAL profile (SP 800-53 Rev. 5.2.0).',
            'expected_controls' => 149,
            'note' => 'Baselines select controls; they do not replace the full catalogue. Public domain in the United States.',
        ],

        'NIST-800-53B-Moderate' => [
            'name' => 'NIST SP 800-53B - Moderate Baseline',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/53b/final',
            'data' => 'resources/data/nist-sp800-53b-moderate.csv',
            'importer' => OscalCsvImporter::class,
            'reference_url' => 'https://csrc.nist.gov/pubs/sp/800/53b/final',
            'description' => 'The NIST SP 800-53B moderate-impact baseline, generated from the official resolved OSCAL profile (SP 800-53 Rev. 5.2.0).',
            'expected_controls' => 287,
            'note' => 'Baselines select controls; they do not replace the full catalogue. Public domain in the United States.',
        ],

        'NIST-800-53B-High' => [
            'name' => 'NIST SP 800-53B - High Baseline',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/53b/final',
            'data' => 'resources/data/nist-sp800-53b-high.csv',
            'importer' => OscalCsvImporter::class,
            'reference_url' => 'https://csrc.nist.gov/pubs/sp/800/53b/final',
            'description' => 'The NIST SP 800-53B high-impact baseline, generated from the official resolved OSCAL profile (SP 800-53 Rev. 5.2.0).',
            'expected_controls' => 370,
            'note' => 'Baselines select controls; they do not replace the full catalogue. Public domain in the United States.',
        ],

        'NIST-800-53B-Privacy' => [
            'name' => 'NIST SP 800-53B - Privacy Baseline',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/53b/final',
            'data' => 'resources/data/nist-sp800-53b-privacy.csv',
            'importer' => OscalCsvImporter::class,
            'reference_url' => 'https://csrc.nist.gov/pubs/sp/800/53b/final',
            'description' => 'The NIST SP 800-53B privacy baseline, generated from the official resolved OSCAL profile. Use it with an impact baseline, not on its own.',
            'expected_controls' => 96,
            'note' => 'Public domain in the United States.',
        ],

        'NIST-800-172r3' => [
            'name' => 'NIST SP 800-172 Rev. 3 - Enhanced Security Requirements for CUI',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/172/r3/final',
            'data' => 'resources/data/nist-sp800-172r3.csv',
            'importer' => OscalCsvImporter::class,
            'reference_url' => 'https://csrc.nist.gov/pubs/sp/800/172/r3/final',
            'description' => 'Enhanced security requirements for protecting controlled unclassified information in critical programmes and high-value assets. Rev. 3, generated from NIST OSCAL content.',
            'expected_controls' => 103,
            'note' => 'Pairs with SP 800-171 Rev. 3. Public domain in the United States.',
        ],

        'NIST-CSF-2.0' => [
            'name' => 'NIST Cybersecurity Framework 2.0',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://www.nist.gov/cyberframework',
            'data' => 'resources/data/nist-csf-2.0.csv',
            'importer' => OscalCsvImporter::class,
            'reference_url' => 'https://www.nist.gov/cyberframework',
            'description' => 'The NIST Cybersecurity Framework 2.0 functions, categories, and subcategories, generated from the official OSCAL catalog (v1.2.1).',
            'expected_controls' => 128,
            'note' => 'Outcome-based, not prescriptive. Map subcategories to SP 800-53 controls rather than treating them as duplicates.',
        ],

        'NIST-AI-RMF-1.0' => [
            'name' => 'NIST AI Risk Management Framework 1.0',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://www.nist.gov/itl/ai-risk-management-framework',
            'data' => 'resources/data/nist-ai-rmf-1.0.csv',
            'importer' => OscalCsvImporter::class,
            'reference_url' => 'https://www.nist.gov/itl/ai-risk-management-framework',
            'description' => 'The NIST AI Risk Management Framework 1.0 functions, categories, and subcategories, generated from NIST OSCAL content.',
            'expected_controls' => 91,
            'note' => 'Voluntary framework. Pair with NIST AI 600-1 for generative AI risk and with your own AI policy.',
        ],

        '800-171r2' => [
            'name' => 'NIST SP 800-171 Rev. 2',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/171/r2/upd1/final',
            'data' => 'resources/data/sp800-171r2.csv',
            'seeder' => 'SP800171r2Seeder',
            'expected_controls' => 110,
            'note' => 'Superseded by Rev. 3 for new assessments; kept for existing programmes.',
        ],

        '800-171r3' => [
            'name' => 'NIST SP 800-171 Rev. 3',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/171/r3/final',
            'data' => 'resources/data/sp800-171r3.csv',
            'seeder' => 'SP800171r3Seeder',
            'expected_controls' => 97,
            'note' => 'Current revision. Requirements map to SP 800-53 controls.',
        ],

        '800-218' => [
            'name' => 'NIST SP 800-218 - Secure Software Development Framework',
            'authority' => 'NIST',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://csrc.nist.gov/pubs/sp/800/218/final',
            'data' => 'resources/data/sp800-218.csv',
            'seeder' => 'SP800218Seeder',
            'expected_controls' => 42,
            'note' => 'Practice-based; use for secure SDLC programmes and SSDF attestations.',
        ],

        'CMMC 2.0 - Level 2' => [
            'name' => 'CMMC 2.0 Level 2',
            'authority' => 'US Department of Defense',
            'licence' => 'Derived from NIST SP 800-171 (public domain)',
            'redistributable' => 'conditional',
            'provenance' => 'https://www.acq.osd.mil/cmmc/',
            'data' => 'resources/data/CMMC-2-L2.csv',
            'seeder' => 'CMMC2L2Seeder',
            'expected_controls' => 110,
            'note' => 'Assessment guidance is DoD material; the control set mirrors SP 800-171.',
        ],

        'DFARS 252.204' => [
            'name' => 'DFARS 252.204-7012',
            'authority' => 'US Department of Defense',
            'licence' => 'Public domain (US Government work)',
            'redistributable' => 'yes',
            'provenance' => 'https://www.acquisition.gov/dfars',
            'data' => 'resources/data/DFAR-252-204.csv',
            'seeder' => 'DFARS252204Seeder',
            'expected_controls' => 16,
            'note' => 'Contract clause requirements, not a full control framework.',
        ],

        'HIPAA-Security' => [
            'name' => 'HIPAA Security Rule',
            'authority' => 'US Department of Health and Human Services',
            'licence' => 'Public domain (US regulation)',
            'redistributable' => 'yes',
            'provenance' => 'https://www.hhs.gov/hipaa/for-professionals/security/',
            'data' => 'resources/data/hipaa-security-rule.csv',
            'seeder' => 'HipaaSeeder',
            'expected_controls' => 49,
            'note' => 'Administrative, physical, and technical safeguards.',
        ],

        'TSC2017a' => [
            'name' => 'Trust Services Criteria 2017',
            'authority' => 'AICPA',
            'licence' => 'AICPA copyright - not freely redistributable',
            'redistributable' => 'review',
            'provenance' => 'https://www.aicpa-cima.com/resources/download/2017-trust-services-criteria-with-revised-points-of-focus-2022',
            'data' => 'resources/data/TSC2017.csv',
            'seeder' => 'TSC2017Seeder',
            'expected_controls' => 61,
            'note' => 'Licensing must be reviewed before redistribution. Common criteria are widely quoted, but the published criteria set is AICPA material.',
        ],

    ],

    /*
     * Bundles reference source documents by filename. OpenGRC does not ship the
     * documents themselves, which keeps copyrighted standards out of the repo.
     * Users supply their own licensed copies.
     */
    'bundles' => [
        'ISO-27001' => ['authority' => 'ISO/IEC', 'licence' => 'Commercial - purchase required', 'redistributable' => 'no'],
        'PCI-DSS' => ['authority' => 'PCI SSC', 'licence' => 'Free download, redistribution restricted', 'redistributable' => 'conditional'],
        '800-171' => ['authority' => 'NIST', 'licence' => 'Public domain (US Government work)', 'redistributable' => 'yes'],
        '800-53-Low' => ['authority' => 'NIST', 'licence' => 'Public domain (US Government work)', 'redistributable' => 'yes'],
        'HIPAA-Security' => ['authority' => 'HHS', 'licence' => 'Public domain (US regulation)', 'redistributable' => 'yes'],
        'CSCv8' => ['authority' => 'CIS', 'licence' => 'CIS Terms of Use', 'redistributable' => 'conditional'],
        'SOC2-Type2' => ['authority' => 'AICPA', 'licence' => 'AICPA copyright', 'redistributable' => 'no'],
    ],

];
