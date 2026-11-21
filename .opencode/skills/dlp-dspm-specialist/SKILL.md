---
name: Data Loss Prevention and DSPM Specialist
description: Use when designing, reviewing or operating data loss prevention (DLP), data security posture management (DSPM), data classification, sensitive-identifier detection, data residency, cloud/SaaS connectors, or the policies and screens around them. Covers on-prem control planes with read-only API access to SaaS, IaaS and PaaS, industry and geography overlays, detection quality, compliance evidence, required menus/settings, and interfaces that keep customer data and customer actions on-prem.
---

# Data Loss Prevention and DSPM Specialist

This skill owns the data-security layer of a GRC programme: where sensitive data
lives, who can reach it, what leaves the boundary, what the detectors actually
catch, and what evidence proves it. It works with the risk and compliance analyst
skill: DLP and DSPM findings are inputs to risk, not a separate universe.

Load this skill with:

- `risk-and-compliance-analyst` — scoring, appetite, heat maps, treatment,
  acceptance, and board reporting.
- `opengrc-security` — auth, uploads, private files, egress, logging.
- `opengrc-privacy` — personal data, retention, rights, cross-border transfer.
- `opengrc-design` and `opengrc-user-interface` — screens and settings.
- `ecosystem-tooling-and-reporting` — cross-product placement and reporting.

## Mandate

Make the data-security programme measurable and defensible:

- Know where sensitive data is, who owns it, and why it exists.
- Classify by data type, industry, jurisdiction, and business purpose, not by a
  single global label.
- Apply policy at the right channel with the least friction that still protects.
- Keep the control plane and customer actions on-prem; treat SaaS, IaaS, PaaS and
  cloud data sites as data planes reached through read-only APIs.
- Measure precision and recall, coverage, freshness, exposure, aging and time to
  respond — not rule counts.
- Produce evidence that an auditor or regulator can follow, and a risk position
  the risk skill can score.

This is not legal advice. Confirm regulatory applicability with counsel in each
jurisdiction, and record the legal basis and review date.

## First principles

1. **Data-centric, not tool-centric.** A DLP rule is not a control until it is
   mapped to a data class, a channel, an owner, and a response.
2. **Unknown is not clean.** An unscanned store is a coverage gap. A low finding
   count with low coverage is an unmeasured programme.
3. **Coverage and freshness precede findings.** A stale mirror describes last
   month. Show last scan, last sync, and scope before any percentage.
4. **A match is not an incident.** Context — identity, destination, data class,
   volume, business process, legal basis — turns a match into a decision.
5. **Detectors have precision and recall.** A detector that flags nothing has a
   perfect false-positive rate and no value. Measure both with a labelled sample.
6. **Policy is a decision, not a regex.** Say who may send what, where, under
   which condition, with what action and exception.
7. **Minimise copies.** Every copy is a new exposure, retention obligation, and
   discovery blind spot. Protect the source and delete the copy.
8. **Keep the customer's data and actions on-prem.** Cloud APIs supply metadata
   and posture; customer content, approvals, quarantines and deletions execute
   in the customer's environment.
9. **Classify for a purpose.** A label with no policy, owner, or evidence is
   decoration.
10. **Design for the person who is blocked.** Explain the rule, the data class,
    the safe alternative, and how to request an exception — without revealing
    the sensitive value.

## Data management methodology

Use this lifecycle for every data domain, system, and vendor.

```
Inventory -> Classify -> Map flow -> Assess exposure -> Protect -> Monitor
    -> Respond -> Retain/Delete -> Evidence -> Review
```

### What every data asset record needs

| Field | Why |
|---|---|
| Asset / dataset / store | The unit that is scanned and owned |
| System, service, environment | Cloud vs on-prem, prod vs non-prod |
| Owner and steward | Accountability and review |
| Data classes and identifiers | What policy applies |
| Jurisdiction and residency | Transfer, localisation, breach rules |
| Business purpose and legal basis | Why it exists; what permits it |
| Access model and entitlements | Who can read, write, share, export |
| Exposure | Public, external share, broad internal, restricted |
| Encryption and key custody | At rest, in transit, BYOK/HYOK |
| Retention and deletion | When it goes and how deletion is proved |
| Lineage and copies | Where it came from and where it leaked to |
| Scan coverage and freshness | What is known vs assumed |
| Risk link | The register entry it feeds |

### Classification dimensions

Classify along several axes, not one label:

- **Sensitivity:** public, internal, confidential, restricted, regulated.
- **Data type:** personal, financial, health, biometric, credential, children's,
  HR, legal privilege, customer content, source code, IP.
- **Identifier type:** the detector-level taxonomy (email, SIN, PAN, PHI, API
  key, and so on).
- **Regulatory regime:** GDPR, HIPAA, PCI DSS, PIPEDA, PHIPA, Law 25, FERPA,
  GLBA, DORA, sector rules.
- **Industry context:** healthcare, financial, retail, education, public sector,
  defence, SaaS/technology.
- **Geography and residency:** where the data is stored, processed, accessed,
  supported, backed up, and disclosed.
- **Purpose and legal basis:** service delivery, contract, consent, legal
  obligation, legitimate interest, vital interest.
- **Retention class:** transient, operational, records, legal hold, archive.

A classification that cannot change a policy or a retention decision is not
useful. Keep the vocabulary small enough to maintain and map vendor labels to it;
never silently guess a vendor tag.

## Industry and geography overlays

Build a **policy pack** per industry x geography x data class. A pack states the
identifiers, obligations, permitted transfers, retention, response times, and
evidence. Check the current text and applicability before relying on any
regulation.

| Industry | Typical sensitive data | Common obligations | Design consequence |
|---|---|---|---|
| Healthcare | PHI, medical record numbers, prescriptions, mental health, genetic, biometric | HIPAA (US), PHIPA (Ontario), PIPEDA (Canada), GDPR Art. 9 (EU) | Strong access control, minimum necessary, audit logs, local processing |
| Financial | PAN, bank accounts, KYC, beneficial ownership, trading data, AML records | PCI DSS, GLBA, OSFI E-21, DORA, SEC/NYDFS rules | Tokenisation, segmentation, retention, resilience, evidence of controls |
| Retail / e-commerce | Consumer PII, payment data, location, credentials | PCI DSS, consumer protection, breach notification | Checkout isolation, fraud signals, short retention |
| Education | Student records, minors, guardians, assessments | FERPA, COPPA, provincial privacy laws | Consent, parental rights, strict access, retention schedules |
| Public sector / defence | CUI, classified, citizen data, procurement | CMMC, privacy acts, security classification | Residency, clearance, supply-chain controls |
| SaaS / technology | Customer content, tenant data, source code, API keys, logs | Contracts, GDPR, data-processing terms, sector rules | Tenant isolation, subprocessor control, egress limits |
| Any | Employee HR, background checks, biometrics, health | GDPR Art. 9/10, works councils, BIPA and similar | Purpose limitation, local review, no AI egress |

### Cross-border and residency checks

- Where is the data stored, processed, supported, backed up, and disaster
  recovered? "Hosted in the EU" is not an answer if support staff are elsewhere.
- Which subprocessors and cloud regions are in the path?
- Is there a transfer mechanism (adequacy, SCCs, BCRs) and a transfer impact
  assessment? Is localisation required?
- Can the customer's authorities compel disclosure? Does the contract address
  government access?
- Are logs, metadata, hashes, and backups in scope of the residency claim?
- Can the customer choose a region, and can that choice be proved?
- For on-prem control planes: what metadata leaves, to which region, under which
  key, and for how long?

Record the decision as a data-flow and a risk entry, not a paragraph in a
spreadsheet.

## Sensitive identifier taxonomy

Keep a versioned catalogue. Every detector and policy refers to a stable code.

| Family | Examples | Detection notes |
|---|---|---|
| Credentials and secrets | API keys, private keys, JWTs, passwords, connection strings | High-confidence patterns; block on egress; never store the value |
| Financial | PAN, IBAN, bank account, SWIFT, crypto address | Luhn/mod-97 and context; test data must not match |
| Government IDs | SSN, SIN, passport, driver's licence, national ID, medical ID | Country-specific checksums and formats; avoid over-broad digits |
| Health | Diagnosis, prescription, lab, mental health, genetic | Mostly contextual; high false-positive rate expected |
| Biometric | Fingerprint, face, voice, iris | Special category; local matching, strict retention |
| Criminal / background | Criminal record, background check | Special category; restricted access, no AI egress |
| HR / personnel | Salary, performance, discipline, leave, union | Contextual; local works-council rules |
| Personal identifiers | Name, email, phone, address, DOB, IP, device ID | High volume; action depends on context and destination |
| Customer / tenant data | Account data, usage, support tickets, content | Contract and tenant isolation are the control |
| Children's data | Student records, age, guardian | Consent and retention; never in general AI prompts |
| IP / commercial | Source code, designs, pricing, M&A | Fingerprinting and EDM; exfil detection |
| Regulated content | CUI, export-controlled, classified | Jurisdiction and clearance controls |

### Detector design

- Prefer the cheapest reliable signal: checksum + format for structured IDs;
  context and proximity for unstructured data; exact data match (EDM) or document
  fingerprinting for known records; ML/NER for ambiguous language.
- Define confidence bands: high-confidence (block), medium (quarantine or flag),
  low (monitor or suppress with expiry).
- Handle Unicode, encodings, line breaks, OCR artefacts, PDFs, images, archives,
  and compressed files explicitly. State what is not inspected.
- Context matters: a test card in a QA file, an example SSN in documentation, a
  public support email, a customer's own data in their tenant.
- Never store the matched value to "improve tuning" without a lawful basis,
  retention, and access control. Prefer hashes, categories, and labels.
- Version every pattern and classifier. A detector change is a control change.

### Quality measurement

```
precision = true positives / (true positives + false positives)
recall    = true positives / (true positives + false negatives)
FP rate   = false positives / reviewed findings        (programme view)
```

- A false-positive rate without a labelled sample is a complaint rate, not a
  quality measure.
- Build a small, representative corpus per identifier and channel: positives,
  negatives, edge cases, multilingual samples, test/placeholder values.
- Track precision, recall, review volume, exception rate, suppression rate,
  time-to-triage, time-to-remediate, and drift.
- A minimum sample is mandatory before declaring a target met. Say "insufficient
  data" rather than "0%".
- Tune the detector, not the report. Re-test after every change; keep history.

## DLP policy design

A policy is an object with an owner, version, scope, action, exception, and
review. It is testable and simulated before it blocks anyone.

```yaml
policy:
  name: Customer PAN to external email
  owner: data-protection-office
  version: 3
  data_classes: [payment_card]
  scope:
    channels: [email, webmail, saas_sharing]
    users: [all]
    destinations: [external]
    tenants: [acme]
  conditions:
    min_confidence: high
    min_count: 1
    context: [not_test_data, not_allowlisted_customer]
  action: block
  severity: critical
  exception:
    allowed: true
    approver: dpo
    max_days: 30
  evidence:
    store: metadata_and_hash_only
  tests:
    positives: [...]
    negatives: [...]
  review: quarterly
```

Rules:

- One policy, one decision. Do not combine unrelated data classes in one rule.
- Define precedence: global block > tenant block > category block > monitor. Make
  conflicts visible and test them.
- Default to monitor for new, untuned detectors; block only high-confidence or
  after a pilot.
- Suppressions and allowlists need an owner, reason, scope, and expiry. An
  eternal allowlist is a bypass.
- Exceptions are approved decisions, not edits to the detector.
- A block must tell the user what class matched, why the destination is risky,
  and how to proceed safely. Do not echo the secret or the full match.
- Log the policy id and version on every finding; a result without the policy
  version cannot be reproduced.

### Channel matrix

| Channel | DLP | DSPM | Notes |
|---|---|---|---|
| Email / webmail | Yes | No | Attachments, body, links; encryption |
| Endpoint (USB, print, clipboard, screen) | Yes | Partial | Agent required; privacy and works councils |
| Web / SaaS upload | Yes | Partial | CASB or API; tenant policy |
| Cloud storage (S3, Blob, GCS, Drive, SharePoint) | Partial | Yes | Inventory, ACLs, sharing, encryption, versions |
| Databases / warehouses | Partial | Yes | Metadata, sampling, classification, access |
| APIs / integrations | Yes | Partial | Payload inspection, schema, rate limits |
| AI prompts / assistants | Yes | No | Hard block for HR, special category, credentials |
| Backups / archives | No | Yes | Often the largest unclassified copy |
| Ticketing / collaboration | Yes | Partial | Attachments, pasted content |
| Code repositories | Yes | Partial | Secrets, customer data, keys |
| Print / physical | Partial | No | Policy, secure print, disposal |

Do not confuse DLP, DSPM, CASB, CSPM, data access governance, insider risk, and
breach detection. They compose; they do not replace each other.

## On-prem control plane with cloud data planes

Target architecture:

```
[SaaS / IaaS / PaaS / cloud data sites]
        |  read-only, scoped APIs, TLS, egress-only
        v
[On-prem connector workers] -> [Normaliser] -> [Classifier / detector]
        -> [Policy engine] -> [Findings + evidence] -> [GRC console]
        -> [Local action runner] -> customer-approved quarantine / delete / ACL fix
```

### Non-negotiables

- **Read-only by default.** Separate credentials per connector, least privilege,
  short-lived where possible. No broad admin keys. No write scopes unless a
  specific, approved action requires one.
- **Outbound-only.** No inbound listener exposed to the cloud. Use an egress
  proxy with an allowlist, private link/VPN where required, and TLS verification
  that is never disabled.
- **Metadata first.** Copy identifiers, owners, classifications, ACL summaries,
  counts, hashes, and timestamps. Do not copy content, rows, samples, or query
  text into the control plane.
- **Customer actions stay on-prem.** Quarantine, deletion, permission changes,
  and key operations run in the customer's environment, with approval, scope
  preview, and an audit trail. A SaaS console must not execute them on the
  customer's behalf.
- **Key custody.** BYOK/HYOK where offered; envelope encryption; separate keys per
  tenant and region; documented rotation and break-glass.
- **Tenant isolation.** One tenant's connector, credentials, cache, and findings
  must not be readable by another. Test the boundary.
- **Residency.** Pin processing, storage, logs, backups, and support access to the
  agreed region. State what still leaves (aggregates, hashes, telemetry) and why.
- **Auditability.** Every connector query, credential use, policy change, export,
  and action is logged with actor, scope, and result.

### Connector patterns

| Target | Pattern | Watch |
|---|---|---|
| Microsoft 365 / Google Workspace | OAuth app, admin consent, audit and drive APIs | Over-broad scopes; shared drives; external sharing |
| Salesforce / SaaS CRM | Connected app, read-only user, metadata + attachment APIs | Sandbox vs prod; field-level security |
| AWS / Azure / GCP | Read-only role, inventory, object ACL, key and log APIs | Cross-account roles; public buckets; region coverage |
| Databases / warehouses | Read-only account, metadata and sampled classification | Sampling bias; PII in column names; views |
| Code repositories | App integration, secret scanning, repo metadata | Forked repos; CI logs; archived repos |
| Endpoints | Agent or EDR integration | Content egress; employee monitoring rules |
| Backups / archives | Catalog API, restore sample | The copy everyone forgets |
| Identity | SCIM/IGA read, group and role graph | Nested groups; stale accounts |

### Scale and reliability

- Incremental sync with cursors/delta tokens; avoid full scans on a timer.
- Rate budgets per API, concurrency limits, retry with jitter, dead-letter queue,
  and a visible last-success time.
- Idempotent upserts keyed by stable external IDs. A replay must not duplicate a
  finding.
- Schema versioning and drift detection. A renamed field is a silent coverage
  loss.
- Partial failure is normal: show which scopes failed, keep the last good data,
  and mark freshness.
- Scan during agreed windows; separate discovery load from production traffic.

## Compliance and detection engine review

Review the engine as a system, not a set of rules. Score each area.

| Area | Questions | Evidence |
|---|---|---|
| Connector health | Scope, credential age, last success, errors, rate limits | Connector inventory and health page |
| Coverage | Which stores, regions, tenants, channels are in scope? | Coverage denominator, not just finding count |
| Freshness | When was each source last scanned? | Last sync per source; staleness threshold |
| Normalisation | Are types, units, timestamps, and IDs consistent? | Schema version and mapping tests |
| Classification | Catalogue version, locale, confidence, overrides | Versioned catalogue and test corpus |
| Policy | Deterministic, versioned, precedence, dry-run | Policy tests and simulation results |
| Detection | Precision, recall, drift, canaries, rule version | Labelled sample and change log |
| Findings | Dedupe, severity, owner, due date, risk link, retest | Finding lifecycle and repeat rate |
| Response | Time to triage, time to remediate, exception rate | Queue metrics and SLA |
| Evidence | Hash, immutability, retention, export labels | Ledger verification |
| Settings | Every knob, safe default, change audit | Settings inventory and audit log |
| Access | Role-based menus and record-level checks | Permission tests |

### Optimisation checklist

- [ ] Every source has an owner, scope, schedule, and last-success timestamp.
- [ ] Credentials are per source, least privilege, rotated, and never logged.
- [ ] Full scans are replaced by incremental sync where the API supports it.
- [ ] Duplicate findings collapse on a stable key; replay is idempotent.
- [ ] The classifier and detector have versioned configurations and a test corpus.
- [ ] New detectors start in monitor; blocking requires precision evidence.
- [ ] Suppressions and allowlists expire and have an owner.
- [ ] A finding links to the data asset, owner, policy version, and risk.
- [ ] A remediation links to a control and a retest date.
- [ ] The dashboard shows coverage, freshness, aging, and exposure before counts.
- [ ] Stale, failed, and partial sources are visible, not silently zero.
- [ ] Exports carry purpose, owner, date, retention, and classification.
- [ ] No raw content, matched value, secret, or personal data is in logs,
      metrics, notifications, or AI prompts.

### Required menus, interfaces, and settings

If a capability is not configurable without a code deploy, it is not a product
capability — unless it is a security default that must not be weakened.

**Data sources**
- Add/edit/test connector, scope selection, schedule, credential rotation, health,
  last sync, errors, region, tenant.

**Classification and identifiers**
- Versioned catalogue; category, jurisdictions, industries, pattern/classifier,
  confidence, severity, target precision/recall, minimum sample, owner, reference;
  a test bench with positives and negatives; change history.

**Policies**
- Data classes, channels, users, destinations, conditions, action, severity,
  owner, legal basis, precedence, exceptions, dry-run/simulation, tests, review
  date.

**Detection and validation**
- Rule list, version, scope, enabled state, test result, failing rules, last
  validation, coverage by technique/data class, change log.

**Findings and response**
- Queue, filters by class/severity/source/owner/age, dedupe, assign, disposition,
  evidence, exception request, remediation task, retest, risk link, bulk safety.

**DSPM posture**
- Coverage, classification rate, exposure, public/external share, unowned data,
  stale data, residency, retention, deletion proof, aging, top assets.

**Exceptions and suppressions**
- Scope, reason, approver, start, expiry, review, revocation, and the findings
  they affect.

**Settings**
- Retention, scan windows, rate limits, staleness thresholds, egress policy,
  AI egress, notification routing, severity thresholds, key management, region,
  data residency, feature flags, and audit retention.

**Reports**
- Board, control owner, auditor, regulator; aggregate by default; labels; no
  small-cell leakage; consistent definitions.

**Audit log and help**
- Who changed a policy, catalog entry, detector, setting, or connector; why.
- A user-facing "why was this blocked?" page with a safe alternative and an
  exception request path.

For each, define: primary role, decision supported, default state, empty/loading/
error/permission-denied states, and the audit event.

## Interface and design rules

Use the OpenCyberRange visual language as the reference for a mature, restrained
security console, adapted to OpenGRC's Filament/Tailwind implementation.

- Slate-and-blue identity; semantic success/warning/danger; no neon, glassmorphism
  or marketing hero layouts for operational screens.
- Dark and light themes both designed. Status is never colour alone; pair with a
  label or icon.
- Inter for UI, JetBrains Mono for hashes, identifiers, paths, and keys.
- Dense operational tables, clear row hierarchy, horizontal scroll on the wrapper,
  not the page. Identifiers wrap; hashes truncate with a copy action.
- Page padding and card spacing from the existing scale; avoid inventing values.
- Every state designed: loading, empty, error, partial, permission denied, stale,
  long content.
- Destructive actions (quarantine, delete, revoke, block) need scope preview,
  confirmation, reason, and a safe default.
- Never show a secret, full card number, full government ID, or raw match value.
  Show category, last four or a hash, and the policy that fired.
- Explain a block in plain language: what class, which policy, which destination,
  what to do next, who to contact, how to request an exception.
- Accessibility: keyboard operation, visible focus, labels, contrast, reduced
  motion, table captions, mobile stacking.
- Aggregate dashboards must respect record permissions and suppress small cells
  where re-identification or commercial sensitivity applies.

## Metrics that mean something

| Metric | Definition | Use |
|---|---|---|
| Coverage | Scanned assets / assets in scope | The denominator; unknown is not clean |
| Freshness | Age of the last successful scan per source | Stale data is not evidence |
| Classification rate | Assets with a current class / in scope | Governance, not a finding count |
| Exposure rate | Assets with public/external/broad access / in scope | Prioritise response |
| Precision / recall | Per identifier and channel, on a labelled sample | Detector quality |
| Finding aging | Open days, by severity and owner | Process health |
| Repeat rate | Findings recurring on the same asset/rule | Root cause and control failure |
| Time to triage / remediate | Median and P90 | Response capability |
| Exception / suppression rate | Share of findings excused, and expiry compliance | Policy drift |
| Evidenced closure | Closed findings with a test and hash | Audit readiness |

Avoid: number of rules, number of alerts, raw finding count without coverage,
"0% false positives" with insufficient data, and dashboards with no owner.

## Workflow when invoked

1. **Clarify context.** Industry, geography, data classes, deployment, cloud
   estate, regulations, risk appetite, and the decision to support.
2. **Inventory and classify.** What data exists, where, who owns it, where it
   flows, and which identifiers matter.
3. **Map controls and evidence.** DLP channels, DSPM coverage, access, encryption,
   retention, response, and the GRC links.
4. **Review the engine and UI.** Use the checklists above; identify missing
   menus, settings, states, and metrics.
5. **Quantify and prioritise.** Link findings to risks; use the risk skill for
   scoring, appetite, and treatment. Separate exposure from detection quality.
6. **Recommend with acceptance tests.** A specific change, the decision it
   improves, the evidence, and how to know it worked.
7. **State limits.** What was not inspected, what is assumed, and what requires
   counsel or a qualified specialist.

## Output contract

```
Observed:   sources, coverage, freshness, counts, policies, identifiers
Inferred:   exposure, quality, risk, and the reasoning
Assumption: jurisdiction, legal basis, scope, data quality
Recommend:  control/policy/UI change, owner, date, acceptance test
Unknown:    what evidence would change the answer
```

Never fabricate a coverage number, a precision rate, or a regulatory obligation.
Label illustrative figures. Distinguish "not measured" from "measured clean".

## OpenGRC baseline (20 November 2026)

Verify against the code before relying on this; it is a starting map, not a
promise.

**Present**
- `DlpService` scans AI egress and uploads. HR, special-category, and credentials
  are blocked before AI; uploads block credentials and flag HR/special category.
  Matched values are not stored; findings keep labels, severity, action, actor,
  and a content hash.
- `config/dlp.php` and `DlpIdentifierCatalog` define identifier types, targets,
  minimum samples, and references. `DlpQualityMetrics` measures reviewed
  false-positive rates; the DLP quality page shows per-type status.
- DLP findings queue in Admin → System; dispositions are the only editable field.
- Data posture page combines OpenDiscoveryEngine mirror, OpenMetadata catalog
  governance, and DLP operations; aggregate-only.
- Evidence sources support read-only connectors with encrypted configuration;
  OpenMetadata is metadata-only; discovery sync is read-only.
- Control validation rules and the evidence ledger provide deterministic status
  from evidence; detection-coverage ingestion accepts YARA/Sigma counts as
  evidence, not execution.

**Highest-value gaps**
- No first-class DLP policy object or policy UI (channels, actions, precedence,
  dry-run, exceptions with expiry). Policy lives in code/config today.
- Identifier catalogue is configuration-only: no versioned UI, test bench,
  industry/geography tags, or per-type owner.
- No exact-data-match, document fingerprinting, or measured recall. The quality
  page measures false positives only.
- DLP findings do not link to risks, controls, or implementations; the risk
  handoff is manual.
- No suppression/exception workflow with scope and expiry.
- No per-industry/geography policy packs or residency metadata on data assets.
- DSPM is a read-only mirror: owners cannot correct classifications, and there is
  no remediation/retention execution.
- Menus are split between Admin (findings) and Discovery (quality/posture); there
  is no unified data-security workspace for analysts.
- No connector-health or scan-coverage settings surface for DLP/DSPM as a whole.

When proposing fixes, follow `AGENTS.md`: append-only migrations, tests, an ADR
for trade-offs, three-role authorization, and no licensed standard text.

## Boundaries

- Not legal advice; applicability and transfer mechanisms need counsel.
- Not a substitute for a qualified privacy officer, actuary, or penetration
  tester.
- Do not use this skill to justify unlimited employee monitoring. Purpose
  limitation, proportionality, works councils, and local law still apply.
- Do not send customer content, secrets, or special-category data to a model or
  a third-party service to "classify" it. Classification runs where the data
  lives.
- Do not weaken a security default (private storage, content checks, MFA,
  read-only connectors) without an ADR.
