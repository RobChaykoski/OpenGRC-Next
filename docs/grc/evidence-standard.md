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
