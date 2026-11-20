# Evidence standard

Evidence is the difference between a compliance claim and a compliance programme.
This standard defines what counts, how it is named, and how long it lives.

## The five-minute test

A stranger with access to OpenGRC should be able to answer "show me this control
works" in under five minutes. If they cannot, the evidence is not stored, it is
merely mentioned.

## Evidence hierarchy

Prefer the highest level available. Level 1 is a claim; level 4 is proof.

| Level | Type | Example |
|---|---|---|
| 1 | Assertion | "Access reviews are performed quarterly" |
| 2 | Documented procedure | The written access review process |
| 3 | System record | The ticket, the exported review, the signed attestation |
| 4 | Machine-verifiable artifact | SSO configuration export, log sample, scan report with timestamp |

A control at level 1 is not implemented, whatever the status field says.

## Acceptable artifacts

| Artifact | Good for | Watch out for |
|---|---|---|
| Screenshot | Configuration state | Add a dated note; screenshots age badly without one |
| System export (CSV/JSON) | Access lists, inventory, findings | Must include a timestamp and source system |
| Log excerpt | Monitoring, detection | Include the query and the date range |
| Signed document | Policy acknowledgement, attestation | Signature must be identifiable |
| Report from a tool | Vulnerability scans, pen tests | Include scope, date, and tool version |
| Ticket or change record | Change management, remediation | Link to the closed state, not the open one |

## Required metadata

Every piece of evidence should answer:

- **What** it is (filename and a one-line description)
- **When** it was produced (date inside the artifact, not the upload date)
- **Who** produced or signed it
- **Where** it came from (system, URL, or tool)
- **Scope** it covers (which system, population, or period)
- **How** someone verifies it (the check a reviewer performs)

If any of these are missing, add a note next to the attachment. An unexplained PDF
is a liability: it looks like evidence without being examinable.

## Naming

```
<control-code>_<artifact-type>_<system>_<YYYY-MM-DD>.<ext>
```

Examples:

```
AC-2_access-review_okta_2026-10-01.csv
CC7.2_log-sample_cloudtrail_2026-09-30.txt
A.8.8_scan-report_nessus_2026-10-15.pdf
```

Consistent names make bulk review possible and make gaps obvious.

## Freshness

Evidence expires. Suggested maximum age, adjust to your programme:

| Evidence type | Max age | Why |
|---|---|---|
| Access review | 3 months | Staff changes continuously |
| Vulnerability scan | 30 days (external), 90 days (internal) | Exposure changes weekly |
| Penetration test | 12 months, or after major change | A stale test is a historical record |
| Restore test | 12 months | Backups rot silently |
| Policy acknowledgement | 12 months, or on revision | People forget and policies change |
| Vendor assessment | 12 months, or on material change | Vendor risk is not static |
| Incident exercise | 12 months | Response capability decays |

The product does not enforce expiry yet (see `docs/grc/README.md`, gap 1). Track it
in a checklist or review calendar until it does.

## Storing evidence in OpenGRC

Today, evidence attaches to:

- **Audit items** — the primary path. A completed item with an attachment and a
  recorded effectiveness is level 3–4.
- **Data request responses** — evidence supplied by the auditee during fieldwork.
- **Audit records** — overall reports and summary artifacts.

Evidence does **not** attach directly to an implementation yet. The workaround is
to place implementation evidence on the audit item that tests it, and reference
the item in the implementation's notes. Record the workaround in your own
documentation so an auditor is not surprised.

## Machine-collected evidence

ADR 0009 adds a second path: a connector reads a source (PatchMon first), a
normaliser emits a vendor-neutral measurement, and the ingestor stores it as an
immutable `evidence_records` row with a canonical SHA-256 payload hash and an
idempotency key. Replays never duplicate a measurement; corrections are new rows
that point at the record they replace.

- A missing measurement is Unknown, never Effective.
- Credentials live encrypted on `evidence_sources` and are never written into a
  payload.
- Collection is read-only: `Http::grcReadOnly()` refuses anything but GET/HEAD
  and does not follow redirects. The one exception is a token-minting POST to a
  path fixed by connector code (ADR 0017).
- Validation rules, findings, the evidence browser, and the PatchMon and Wazuh
  connectors are built; scheduled collection is not.

### PatchMon source

Admins configure a source under **Admin → System → Evidence sources**:

| Field | Value |
|---|---|
| API base URL | The PatchMon API root, for example `https://patchmon.example/api/v1/api` |
| Username / password | Scoped Basic Authentication. The password is write-only in the UI. |
| Primary control | Optional code such as `SI-2`, used for display and direct mapping. |
| Timeout | Per-request timeout in seconds. |

The connector reads `/hosts`, then per host `/stats`, `/system`, `/package_reports`,
and `/agent_queue`. It does not pull `/packages`; the full list is large and the
rules use counts, report status, and agent health. A host whose per-host calls
fail is stored as Unknown with the error, so one unreachable machine does not
discard the rest of the fleet.

The **Run collection** action queues `CollectEvidenceJob`; the worker writes the
records. `php artisan opengrc:evidence:collect --source=ID` runs the same path
synchronously for operators.

### Wazuh source

The Wazuh connector reads the manager API on port 55000, not the indexer. It
lists agents with `GET /agents` and reads one SCA summary per agent with
`GET /sca/{agent_id}`. One record is stored per agent, with every policy's
`pass`/`fail`/`invalid` counts in the payload. A disconnected agent or an
incomplete scan is Unknown; a policy failure is Failed.

Configure it with a read-only Wazuh user that has `agent:read` and `sca:read` on
`agent:id:*`. Wazuh's default API certificate is self-signed for `localhost`, so
the source needs a CA bundle (a PEM path on the OpenGRC host) and a certificate
with a matching SAN. TLS verification is never disabled. The full runbook,
including the certificate and RBAC commands, is
`docs/integrations/wazuh-evidence.md`.

### OpenMetadata source

The OpenMetadata connector reads `GET /v1/tables` with a read-only bot token and
aggregates catalog governance per database service: tables, columns, owned
tables, classified tables, coverage percentages and classification tag counts.
It requests metadata fields only; sample data, profiling values and table rows
are never fetched. One record is stored per service, so a re-run measures the
same service over time.

A table counts as classified when the table or any column carries a tag in a
configured classification namespace (default `PII`). A curated tag map turns a
full tag FQN into an OpenGRC data-classification code; unknown tags are counted
but not guessed. A table without an owner or a classification makes the service
Failed; an empty catalog is Unknown; a truncated or unreachable catalog fails
the collection. The runbook is `docs/integrations/openmetadata.md`, and the
boundary is ADR 0020.

### Detection coverage source

YARA and Sigma are not connectors; they are formats executed by a detection
platform. The platform pushes a hash-verified `detection-coverage` snapshot to
`POST /api/v1/evidence` with counts for active Sigma and YARA rules, failing
rules, ATT&CK techniques covered and validation age. Rule bodies, samples and
log lines are never sent.

The record status is Unknown. A validation rule decides the control, for example
`failingRules = 0` and `lastValidatedAgeDays <= 7`. A failed delivery leaves the
previous snapshot in place and visible as stale. See
`docs/integrations/detection-coverage.md` and ADR 0021.

### Adversary emulation source

Apache Caldera and similar engines run in OpenCyberRange, not in OpenGRC. After
an operation, the range pushes a hash-verified `adversary-emulation` summary with
techniques attempted, succeeded, detected, prevented and missed. It contains no
commands, payloads, credentials, agent tokens, raw logs or target hostnames.

The record status is Unknown; a validation rule decides the detection or
prevention objective. `techniquesAttempted`, `undetectedTechniques` and
`unpreventedTechniques` are producer-computed because the rule language compares
a path to a constant, not two paths. See
`docs/integrations/adversary-emulation.md` and ADR 0022.

### Web security testing source

OWASP WSTG is a manual test methodology, not a scanner. A tester or assessment
platform pushes a hash-verified `security-testing` summary: methodology and
version, application, environment, tests planned/executed/passed/failed and the
failed test IDs. It contains no request or response bodies, tokens or personal
data, and OpenGRC does not copy the guide.

The record status is Unknown; a validation rule decides the control, for example
`coveragePercent >= 90` and `testsFailed = 0`. Prefer version-qualified IDs such
as `WSTG-v42-ATHN-01`, and record the application version and environment so a
later release is not treated as tested. See
`docs/integrations/security-testing.md` and ADR 0023.

### Software supply chain source

A CycloneDX SBOM is imported as a point-in-time document with its hash, format,
spec version and component coordinates. OSV is queried in batches by purl
ecosystem and version; advisory details are read by id. A vulnerable component
creates a finding with a stable dedupe key, severity, fixed version and due date.

No source, container layer or build artifact is stored. The batch query is the
only POST, through the read-query guard. A re-scan updates the same finding; a
resolved finding reopens if the component is still vulnerable. See
`docs/integrations/sbom-osv.md` and ADR 0028.

### Continuous verification

A rule is data, not code. It names the evidence type it applies to and a list of
assertions:

```json
{
  "match": { "evidence_type": "patch-compliance" },
  "assert": [
    { "path": "securityUpdates", "op": "eq", "value": 0 },
    { "path": "reportAgeDays", "op": "lt", "value": 2 },
    { "path": "agentReporting", "op": "eq", "value": true }
  ]
}
```

Collect with `php artisan opengrc:evidence:collect` (or `--source=ID`). The runner
creates an evidence job, ingests each measurement, and records duplicates and
per-record failures without discarding the rest of the batch. It only reads: the
connector may not issue anything but GET or HEAD.

Run `php artisan opengrc:evidence:validate`. It records one control validation per
rule and asset, marks drift when the status changes, and opens or resolves a
finding. A missing value is Unknown and never closes a finding; only a passing
check does. Supported operators: `eq`, `ne`, `gt`, `gte`, `lt`, `lte`, `in`,
`not_in`, `contains`, `exists`, `missing`.

`opengrc:doctor` warns when an enabled source has no registered connector, has
never run, or is older than `evidence.stale_after_days`.

Admins edit rules under **Admin → System → Validation rules**. The rule
definition is JSON; the editor validates that it is an object before saving.

## Redaction and safety

- Never store credentials, API keys, session tokens, or personal data beyond what
  the control needs.
- Redact the minimum and record that you redacted, so the reviewer knows the
  artifact is not raw.
- Keep the unredacted original only if policy requires it, in the same access-
  controlled location as the rest of the evidence.
- Treat evidence export as a data transfer: check where it goes.

## Chain of custody

Evidence is append-only once the instance is in production mode. Every upload
records a SHA-256 hash and a ledger entry; the entries form a hash chain per
evidence record. Editing the file is detectable, and a database edit of the
ledger is blocked by a trigger.

- Correct a mistake by **withdrawing** the evidence with a reason, then submitting
  the corrected file.
- Replace evidence by **superseding** it with the new file; both records remain.
- Never delete an evidence record or point it at a different file in production.
- Run `php artisan opengrc:evidence:verify` as part of the audit readiness check.
  A failure is an audit finding, not a housekeeping task.

## Quality checklist

- [ ] Level 3 or higher for every control claimed as implemented.
- [ ] Artifact includes a date inside it, not only an upload date.
- [ ] Scope is stated: which systems, which population, which period.
- [ ] The reviewer's check is obvious without asking the owner.
- [ ] No secrets or unnecessary personal data.
- [ ] Name follows the convention.
- [ ] Age is within the freshness window.
- [ ] Linked to an implementation or audit item, not floating in a folder.

## Anti-patterns

| Anti-pattern | Why it fails |
|---|---|
| Screenshot with no context | Cannot be tied to a date, system, or population |
| Policy PDF as evidence of operation | Proves intent, not execution |
| Evidence of one system standing in for a fleet | Scope misrepresentation |
| Evidence collected the week before the audit only | Shows a point-in-time performance |
| Owner's own unsupported statement | Level 1 dressed as level 3 |
| Reused evidence across unrelated controls | Erodes trust in the whole file |
