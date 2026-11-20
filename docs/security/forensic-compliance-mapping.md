# Forensic evidence compliance mapping

This maps the forensic controls to the outcomes and frameworks OpenGRC already
maintains. It is a technical control map, not legal advice; legal requirements
vary by jurisdiction and counsel must approve the process.

Canonical outcomes come from `config/framework_scoping.php`. Framework
identifiers come from `config/framework_catalog.php`; ISO/IEC, PCI DSS and AICPA
text is referenced, not bundled.

## Control families

| # | Forensic control | Platform outcome | Primary evidence |
|---|---|---|---|
| 1 | Authorisation, scope and jurisdiction | CTRL-IR-001 | `forensic-case-authorization` |
| 2 | Collection and extraction | CTRL-LOG-001 | `forensic-collection` |
| 3 | Integrity and sealing | CTRL-LOG-001, CTRL-CRYPTO-001 | `forensic-manifest` |
| 4 | Chain of custody | CTRL-IR-001, CTRL-LOG-001 | `forensic-custody` |
| 5 | Access control and separation of duties | CTRL-ACCESS-001, CTRL-ACCESS-003 | `forensic-access` |
| 6 | Storage and encryption | CTRL-CRYPTO-001 | `forensic-storage` |
| 7 | Retention and legal hold | CTRL-PRIV-001, CTRL-LOG-001 | `forensic-retention` |
| 8 | Deletion and destruction | CTRL-PRIV-002, CTRL-LOG-001 | `forensic-destruction` |
| 9 | Monitoring and incident review | CTRL-LOG-001, CTRL-IR-001 | `forensic-verification` |
| 10 | Privacy and minimisation | CTRL-PRIV-001, CTRL-PRIV-003 | `forensic-collection` |
| 11 | Audit and reporting | CTRL-GOV-001, CTRL-LOG-001 | `forensic-audit-report` |

## Framework mapping

Representative identifiers, not an exhaustive crosswalk. Import the framework
you need before creating rules.

| Forensic control | NIST 800-53 r5 | ISO 27001:2022 | SOC 2 TSC | CIS CSC v8 | HIPAA Security Rule | PCI DSS 4.0 | CMMC 2.0 L2 |
|---|---|---|---|---|---|---|---|
| Authorisation and scope | IR-4, IR-8, AC-3, PT-2 | A.5.24, A.5.26, A.5.34 | CC7.3, CC7.4, P4.1 | 17.1, 17.2, 17.3 | §164.308(a)(6), §164.502(b) | 12.10.1, 12.10.2 | IR.L2-3.6.1, IR.L2-3.6.2 |
| Collection and extraction | AU-2, AU-3, AU-12, IR-4 | A.8.15, A.8.16 | CC7.2, CC7.3 | 8.1, 8.2, 8.5 | §164.312(b), §164.308(a)(1)(ii)(D) | 10.2, 10.3 | AU.L2-3.3.1, AU.L2-3.3.2 |
| Integrity and sealing | AU-9, AU-10, SI-7 | A.8.15, A.8.24 | CC7.2, PI1.4 | 8.3, 8.10 | §164.312(c)(1) | 10.3, 10.5 | AU.L2-3.3.8, SI.L2-3.14.1 |
| Chain of custody | AU-9, AU-10, MP-4 | A.5.28, A.8.15 | CC7.2, CC7.3 | 8.3, 8.6 | §164.312(b), §164.316(b)(2) | 10.3, 10.5 | AU.L2-3.3.8, MP.L2-3.8.4 |
| Access and separation of duties | AC-2, AC-3, AC-4, AC-6 | A.5.15, A.5.18, A.8.3 | CC6.1, CC6.3 | 5.1, 5.4, 6.1, 6.8 | §164.312(a)(1), §164.308(a)(4) | 7.2, 7.3, 8.2 | AC.L2-3.1.1, AC.L2-3.1.5 |
| Storage and encryption | SC-12, SC-13, SC-28 | A.8.24 | CC6.1, CC6.7, C1.1 | 3.10, 3.11 | §164.312(a)(2)(iv), §164.312(e)(2)(ii) | 3.5, 3.6, 4.2 | SC.L2-3.13.10, SC.L2-3.13.11 |
| Retention and legal hold | AU-11, MP-6, PT-2 | A.5.33, A.8.10 | CC7.5, P4.2, P6.1 | 3.1, 3.4, 8.10 | §164.316(b)(2), §164.530(j) | 10.7, 3.2.1 | AU.L2-3.3.9, MP.L2-3.8.3 |
| Deletion and destruction | MP-6, SI-12 | A.8.10, A.7.14 | CC7.5, P4.3 | 3.1, 3.2 | §164.310(d)(2)(i), §164.316(b)(2) | 9.4.6, 10.7 | MP.L2-3.8.3, MP.L2-3.8.4 |
| Monitoring and verification | AU-6, AU-7, SI-4 | A.8.16, A.8.17 | CC4.1, CC7.1, CC7.2 | 8.11, 8.12, 13.1 | §164.308(a)(1)(ii)(D), §164.312(b) | 10.4, 10.6, 11.5 | AU.L2-3.3.3, SI.L2-3.14.6 |
| Privacy and minimisation | PT-2, PT-3, SI-12 | A.5.34, A.8.11, A.8.12 | P4.1, P4.2, P6.1 | 3.1, 3.13 | §164.502(b), §164.514 | 3.4, 4.1 | AC.L2-3.1.22, MP.L2-3.8.5 |
| Audit and reporting | AU-6, AU-7, CA-7 | A.5.35, A.8.16 | CC4.1, CC7.4 | 8.11, 17.7 | §164.308(a)(1)(ii)(D), §164.316(b)(1) | 10.4, 12.10.4 | AU.L2-3.3.3, CA.L2-3.12.1 |

Privacy regimes (where the deployment is in scope): GDPR Art. 5(1)(e) storage
limitation, Art. 5(1)(f) integrity and confidentiality, Art. 30 records, Art. 32
security, Art. 33/34 breach; PIPEDA Principles 4.5, 4.7, 4.8; PHIPA retention,
access and breach provisions. These are references, not a legal opinion.

## Evidence contract

The forensic collector emits these evidence types. OpenGRC stores the record,
hash and validation result; raw content stays in the forensic store.

| `evidence_type` | Required fields | Validation intent |
|---|---|---|
| `forensic-case-authorization` | `caseId`, `legalBasis`, `jurisdiction`, `scope`, `custodian`, `approvedBy`, `authorizedAt`, `retentionDays`, `legalHold` | A case exists, is approved, scoped and attributable |
| `forensic-collection` | `caseId`, `collector`, `collectorSha256`, `platform`, `startedAt`, `finishedAt`, `artifactCount`, `scope`, `timeSynced` | Collection is documented, in scope, from a known tool |
| `forensic-manifest` | `caseId`, `manifestSha256`, `hashAlgorithm`, `artifactCount`, `sealedAt`, `sealedBy`, `chainHead` | The artefact set is sealed and hash-addressed |
| `forensic-custody` | `caseId`, `event`, `actor`, `at`, `artifact`, `hash`, `previousEventHash`, `eventHash` | The chain is append-only and unbroken |
| `forensic-access` | `caseId`, `actor`, `action`, `artifact`, `at`, `mfa`, `approvedBy` | Access is authorised, logged and reviewed |
| `forensic-storage` | `caseId`, `encryptedAtRest`, `encryptedInTransit`, `worm`, `keyReference`, `region` | Storage and key controls are stated |
| `forensic-retention` | `caseId`, `retentionDays`, `legalHold`, `reviewAt`, `policyRef` | Retention is deliberate and holds are honoured |
| `forensic-destruction` | `caseId`, `destroyedAt`, `approvedBy`, `method`, `certificateSha256`, `backupsCovered` | Destruction is approved, complete and evidenced |
| `forensic-verification` | `caseId`, `manifestSha256`, `verifiedAt`, `verifiedBy`, `result`, `artifactsChecked`, `mismatches` | The evidence still matches the sealed manifest |
| `forensic-audit-report` | `caseId`, `period`, `casesOpened`, `casesClosed`, `holds`, `destructions`, `exceptions` | The programme is reviewed and reported |

## Validation rules

Store these as `ControlValidationRule` definitions against the mapped control.
A missing value is `Unknown`, never a pass.

```json
{
  "match": { "evidence_type": "forensic-manifest" },
  "assert": [
    { "path": "hashAlgorithm", "op": "eq", "value": "sha256" },
    { "path": "artifactCount", "op": "gt", "value": 0 },
    { "path": "manifestSha256", "op": "exists" },
    { "path": "sealedAt", "op": "exists" }
  ]
}
```

```json
{
  "match": { "evidence_type": "forensic-retention" },
  "assert": [
    { "path": "retentionDays", "op": "gte", "value": 365 },
    { "path": "policyRef", "op": "exists" }
  ]
}
```

For a case under legal hold, evaluate a second rule that requires
`legalHold = true` and suppresses destruction; do not let a retention-expiry rule
close a held case.

## Coverage: automatic, manual, gap

| Control | Status | Notes |
|---|---|---|
| Manifest hash and artifact count | Automatic | Collector emits `forensic-manifest`; OpenGRC validates |
| Custody hash chain | Automatic | Verifier recomputes `eventHash`; a break fails the rule |
| Collector identity and version | Automatic | `collectorSha256`, tool inventory in the manifest |
| Access events and MFA | Automatic if emitted | Requires the case service to log every read/export |
| Retention metadata | Automatic | Rule checks `retentionDays` and `policyRef` |
| Legal hold | Manual + automatic display | Legal decides; OpenGRC records and blocks destruction |
| Authorisation and scope | Manual | Warrant/consent/policy; approver recorded |
| Privacy review and minimisation | Manual | DPIA/PIA and redaction notes |
| Dual control for export/destruction | Manual + access log | Two approvers; event recorded |
| WORM/Object Lock storage | Gap | Requires storage configuration and a storage attestation |
| RFC 3161 trusted timestamp | Gap | Optional external TSA; record token hash |
| Hardware-backed signing | Gap | Optional HSM/TPM; record key reference |
| Deletion certificate | Gap | Collector/store must produce; platform validates hash |
| Framework import | Setup | Only CSCv8 is imported in the local dev DB; import the rest |

## Importing the frameworks

```bash
php artisan opengrc:framework-import --list
php artisan opengrc:framework-import NIST-800-53r5
php artisan opengrc:framework-import ISO-27001
php artisan opengrc:framework-import TSC2017a
php artisan opengrc:framework-import HIPAA-Security
php artisan opengrc:framework-import 'CMMC 2.0 - Level 2'
```

ISO/IEC 27001, PCI DSS and AICPA TSC text is referenced, not bundled; the
deployment must hold its own licensed copy where required. CIS controls ship
with attribution under the CIS terms.

## Auditor checklist

- [ ] Case authorization names the legal basis, jurisdiction, scope, custodian
      and approver.
- [ ] Every artifact has a SHA-256 in the sealed manifest.
- [ ] The manifest hash matches the sidecar and the custody chain head.
- [ ] The chain of custody has no gaps and no edits; corrections are new events.
- [ ] Access to evidence required MFA and was logged; export used dual control.
- [ ] Retention is stated, legal holds override expiry, and destruction has a
      certificate.
- [ ] The collector version and binary hash are recorded.
- [ ] The verification run is recorded after storage and before export.
- [ ] Privacy review records minimisation and redaction.
- [ ] The control mapping is imported and the validation rules are enabled.

## What the platform does not do

OpenGRC validates and records the evidence; it does not itself image disks,
acquire memory or guarantee admissibility. Those are collector and legal-process
responsibilities. The next build is the case service and the collector that emit
the evidence types above.
