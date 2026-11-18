# 0009 — Continuous control monitoring and vendor-neutral evidence connectors

Status: accepted
Date: 2026-11-17

## Context

Controls are currently tested by people uploading evidence. The platform should
also accept machine evidence from the tools that already measure the control:
patch state, vulnerability scans, endpoint telemetry, identity posture.

Adding one table per vendor would make the GRC model depend on whichever tools a
customer buys. The evidence pipeline must be vendor-neutral: a connector reads a
source, normalises a measurement, and writes an evidence record that references an
asset and (when known) a control.

PatchMon is the first connector because it covers the full lifecycle: asset,
technical measurement, evidence, validation, compliance result, finding, and
remediation.

## Decision

### Pipeline

```
source (PatchMon, Wazuh, Graph, Defender, Nessus, CrowdStrike)
  -> connector (vendor-specific auth and mapping)
  -> evidence record (normalised, hashed, immutable)
  -> validation rule (per control/source)
  -> control state (effective / failed / unknown)
  -> finding + remediation when failed
```

### Schema

- `evidence_sources`: name, type, enabled, encrypted configuration, schedule,
  last run, owner.
- `evidence_jobs`: source, started/finished, status, counts, error. One row per
  collection attempt; this is operational history, not evidence.
- `evidence_records`: source, external id, asset, control (nullable until
  mapped), evidence type, status, collected_at, payload JSON, payload SHA-256,
  idempotency key, ingested_at. Append-only.
- `control_validation_rules`: control, source, rule definition (JSON), severity,
  enabled, version.
- `control_validations`: control, source, evaluated_at, status, result JSON,
  rule version. Historical results are kept.

### Rules

- Idempotency: `(source_id, external_id, collected_at)` is unique. Replays update
  the job, never duplicate evidence.
- A missing measurement is `unknown`, never `effective`.
- Validation is deterministic: same payload + same rule version = same result.
- Failed validation creates a finding with severity, owner (from the control or
  asset), and due date. It does not silently rewrite the implementation status.
- Evidence records are immutable. Corrections are new records with a later
  `collected_at`, linked to the corrected record.
- Credentials are encrypted; never logged, never returned to the browser.
- The connector's base URL is operator-configured and validated. No
  user-supplied URL fetch (SSRF).
- Payloads are size-limited and redacted of secrets before storage.

### First connector: PatchMon

Endpoints used: `/api/hosts`, `/api/hosts/{id}/stats`, `/api/hosts/{id}/packages`,
`/api/hosts/{id}/package_reports`, `/api/hosts/{id}/system`,
`/api/hosts/{id}/agent_queue`, with scoped Basic Authentication.

Normalised record:

```json
{
  "source": "patchmon",
  "asset": "ubuntu-web-01",
  "control": "SI-2",
  "evidenceType": "patch-compliance",
  "compliant": false,
  "securityUpdates": 12,
  "criticalUpdates": 3,
  "capturedAt": "2026-11-17T09:00:00Z"
}
```

Example rule: `security_updates = 0`, `critical_updates = 0`,
`update_age_days < 30`, `agent_reporting = true`.

### Control mapping

Rules attach to the framework identifiers already in the catalog, for example
ISO 27001 A.8.8, NIST CSF PR.PS, NIST 800-53 SI-2, CIS Control 7, SOC 2 CC7.1,
PCI DSS 6.3.3/6.3.4, HIPAA Security Management Process. The mapping is data, not
code; it must be reviewable and survive framework version changes.

### First slice implemented

The foundation is in place: the five tables, append-only evidence records with a
database trigger, the `EvidenceConnector` contract, `NormalisedEvidence`, the
connector registry, the ingesting service, and `Http::grcReadOnly()` for outbound
reads. The collection runner and `opengrc:evidence:collect` are built. Validation
rules, per-asset control validations, drift detection, and first-class findings
are built; `opengrc:evidence:validate` runs them. Findings and evidence have
Filament screens, and `opengrc:doctor` checks connector presence and staleness.

The PatchMon connector and the per-connector source editor are built. The
connector reads hosts, stats, system state, the latest package report, and the
agent queue. It deliberately does not pull the full package list: that payload is
large and the control rules need counts and report freshness, not every package.
The source editor keeps the password write-only and the Run collection action
queues `CollectEvidenceJob`.

Not built yet: scheduled collection and the remaining vendor connectors.

## Consequences

- New connectors implement one interface and one normaliser; they do not touch
  the GRC schema.
- Automated findings are deduplicated per rule, source, and asset: a repeat
  failure updates the open finding, and a pass resolves only that asset's
  finding.
- The first validation implementation reduces the latest record per asset in PHP.
  Move that into SQL before a fleet reaches tens of thousands of records.
- The dashboard can attribute every automated result to a source, a rule version,
  and a raw payload hash.
- Connector outages produce `unknown`, not false compliance.
- Automated findings increase volume; owners and due dates must be set before a
  connector is enabled.
- PatchMon payload shape is vendor-specific; the normaliser is the only place
  that knows it.

## Alternatives considered

- **Import PatchMon directly into control tables.** Rejected: vendor coupling and
  no audit trail.
- **One connector table per vendor.** Rejected: schema churn and inconsistent
  validation.
- **Treat every measurement as pass/fail.** Rejected: missing data would look
  compliant.
- **Put labs and scenarios in the same pipeline.** Rejected: labs run in
  OpenCyberRange (ADR 0002); OpenGRC consumes their evidence through the same
  record contract.
