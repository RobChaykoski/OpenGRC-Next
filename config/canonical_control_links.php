<?php

/**
 * Curated links from imported framework controls to canonical outcomes.
 *
 * A link says "this published requirement is one way the organisation meets
 * this outcome". It is not a claim that two clauses are textually equivalent,
 * and it is not compliance evidence.
 *
 * Rules for editing this file:
 *  - Only add a link when a reviewer can point at the requirement text and say
 *    the outcome is what it asks for. When in doubt, leave it out: an unlinked
 *    control shows up as a coverage gap, which is honest; a guessed link hides
 *    a gap.
 *  - Framework codes come from config/framework_catalog.php; control codes must
 *    match the imported identifier exactly, including punctuation.
 *  - This file is authoritative only for `source = config` links. Links people
 *    make in the UI are never removed by `opengrc:catalog:link`.
 *  - Large catalogues are intentionally partial. NIST SP 800-53 has over a
 *    thousand clauses; bulk-mapping it would be guessing at scale.
 *
 * See docs/grc/control-catalogue-linking.md and ADR 0013.
 */
return [

    'CSCv8' => [
        'CSC-1' => ['CTRL-ASSET-001'],
        'CSC-2' => ['CTRL-ASSET-001'],
        'CSC-5' => ['CTRL-ACCESS-001'],
        'CSC-6' => ['CTRL-ACCESS-001'],
        'CSC-7' => ['CTRL-VULN-001'],
        'CSC-8' => ['CTRL-LOG-001'],
        'CSC-11' => ['CTRL-BCP-001'],
        'CSC-13' => ['CTRL-LOG-001'],
        'CSC-14' => ['CTRL-TRAIN-001'],
        'CSC-15' => ['CTRL-VENDOR-001'],
        'CSC-16' => ['CTRL-SDLC-001'],
        'CSC-17' => ['CTRL-IR-001'],
        'CSC-18' => ['CTRL-VULN-001'],
    ],

    'NIST-CSF-2.0' => [
        'GV.OC-03' => ['CTRL-GOV-001'],
        'GV.PO-01' => ['CTRL-GOV-001'],
        'GV.PO-02' => ['CTRL-GOV-001'],
        'GV.RM-01' => ['CTRL-RISK-001'],
        'GV.RM-02' => ['CTRL-RISK-001'],
        'GV.RM-03' => ['CTRL-RISK-001'],
        'GV.RM-06' => ['CTRL-RISK-001'],
        'GV.RR-02' => ['CTRL-GOV-001'],
        'GV.SC-01' => ['CTRL-VENDOR-001'],
        'GV.SC-02' => ['CTRL-VENDOR-001'],
        'GV.SC-04' => ['CTRL-VENDOR-001'],
        'GV.SC-05' => ['CTRL-VENDOR-001'],
        'GV.SC-07' => ['CTRL-VENDOR-001'],
        'GV.SC-10' => ['CTRL-VENDOR-001'],
        'ID.AM-01' => ['CTRL-ASSET-001'],
        'ID.AM-02' => ['CTRL-ASSET-001'],
        'ID.AM-04' => ['CTRL-ASSET-001'],
        'ID.AM-05' => ['CTRL-ASSET-001'],
        'ID.AM-07' => ['CTRL-ASSET-001'],
        'ID.RA-01' => ['CTRL-VULN-001'],
        'ID.RA-04' => ['CTRL-RISK-001'],
        'ID.RA-05' => ['CTRL-RISK-001'],
        'ID.RA-06' => ['CTRL-RISK-001'],
        'ID.RA-08' => ['CTRL-VULN-001'],
        'ID.RA-10' => ['CTRL-VENDOR-001'],
        'PR.AA-01' => ['CTRL-ACCESS-001'],
        'PR.AA-03' => ['CTRL-ACCESS-002'],
        'PR.AA-05' => ['CTRL-ACCESS-001'],
        'PR.AT-01' => ['CTRL-TRAIN-001'],
        'PR.AT-02' => ['CTRL-TRAIN-001'],
        'PR.DS-01' => ['CTRL-CRYPTO-001'],
        'PR.DS-02' => ['CTRL-CRYPTO-001'],
        'PR.DS-11' => ['CTRL-BCP-001'],
        'PR.IR-03' => ['CTRL-BCP-001'],
        'PR.PS-04' => ['CTRL-LOG-001'],
        'PR.PS-06' => ['CTRL-SDLC-001'],
        'DE.AE-08' => ['CTRL-IR-001'],
        'DE.CM-01' => ['CTRL-LOG-001'],
        'DE.CM-09' => ['CTRL-LOG-001'],
        'RS.AN-03' => ['CTRL-IR-001'],
        'RS.AN-07' => ['CTRL-IR-001'],
        'RS.CO-02' => ['CTRL-IR-001'],
        'RS.CO-03' => ['CTRL-IR-001'],
        'RS.MA-01' => ['CTRL-IR-001'],
        'RS.MA-02' => ['CTRL-IR-001'],
        'RS.MA-03' => ['CTRL-IR-001'],
        'RS.MI-01' => ['CTRL-IR-001'],
        'RS.MI-02' => ['CTRL-IR-001'],
        'RC.RP-01' => ['CTRL-BCP-001'],
        'RC.RP-03' => ['CTRL-BCP-001'],
        'RC.RP-05' => ['CTRL-BCP-001'],
    ],

];
