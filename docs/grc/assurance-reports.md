# Assurance reports and attestations

SOC 1, SOC 2, SOC 3, ISO/IEC 27001 certificates, PCI DSS AOCs and CMMC
affirmations are **reports about controls**, not control catalogues. The register
makes the assurance claim inspectable: what was assessed, by whom, over which
period, with what opinion and exceptions, and whether it is still current.

See ADR 0025 for the boundary. SOC 2 uses the Trust Services Criteria, which is a
framework in the catalogue; the report is the opinion on that framework for a
scope and period.

## What belongs in the register

| Type | What it is | What it is not |
|---|---|---|
| SOC 1 Type 1/2 | ICFR attestation over a service organisation | A control catalogue |
| SOC 2 Type 1/2 | Trust Services Criteria report over a system and period | A permanent certification |
| SOC 3 | General-use report derived from SOC 2 | A detailed control matrix |
| ISO/IEC 27001 | Certification decision by a certification body | The ISO control text |
| ISO/IEC 27701 | Privacy information management certification | A privacy law |
| PCI DSS AOC/ROC | Attestation of compliance or report on compliance | The PCI DSS standard |
| CMMC affirmation | Annual senior-official affirmation | A C3PAO assessment report |

Do **not** add GDPR, CCPA, PIPEDA, FERPA or DORA as report types. They are laws and
regulations; their evidence is a privacy or regulatory programme, not an
attestation report.

## Required fields

| Field | Why it exists |
|---|---|
| Report name | What a reader recognises |
| Type | SOC 1/2/3, ISO, PCI, CMMC or other |
| Scope | The system, service or population covered |
| Provider | Auditor or certifying body |
| Period start / end | The period the opinion covers |
| Outcome | Unqualified, qualified, adverse, disclaimer, compliant, non-compliant or not assessed |
| Owner | Who is accountable for the claim and the next review |
| Status | Current, expired, superseded or withdrawn |

Optional fields: issued date, explicit expiry, bridge letter date, next review,
vendor, framework code, summary, exceptions, document reference and SHA-256.

## Lifecycle

| State | Meaning | How it changes |
|---|---|---|
| Current | The report is the latest for its scope | Created; a new report supersedes it |
| Expired | The explicit expiry date passed | Daily command |
| Superseded | A newer report covers the same scope | Operator |
| Withdrawn | The report was pulled or is no longer relied on | Operator |

A report with no explicit expiry stays Current until superseded or withdrawn. Its
period end and next review date are still visible; use them to decide when the next
report is due.

```bash
php artisan opengrc:attestations:expire --dry-run
php artisan opengrc:attestations:expire
```

The scheduler runs the command daily at 00:15.

## Outcome

| Outcome | Meaning | What to do |
|---|---|---|
| Unqualified | A positive opinion with no qualifications | Record and monitor the next period |
| Qualified | A positive opinion with stated exceptions | Read the exceptions; create a finding or a risk if they matter |
| Adverse | A negative opinion | Do not use the report as positive assurance; escalate |
| Disclaimer | The auditor could not express an opinion | Treat as no assurance until resolved |
| Compliant | A PCI AOC or similar positive conclusion | Record the scope and the next assessment |
| Non-compliant | A negative compliance conclusion | Escalate and create a finding |
| Not assessed | The report is registered but the opinion is not yet read | Read it; do not let it sit |

A qualified opinion is not a pass. It is a report with a caveat, and the caveat is
the part an auditor will ask about.

## Review cadence

| Frequency | Activity |
|---|---|
| Monthly | Review reports expiring or due for review in the next 60 days |
| Quarterly | Confirm current reports still match the in-scope vendors and systems |
| Annually | Re-baseline the assurance map; confirm what the organisation relies on |
| On change | Register a new report when a scope, vendor or system changes |

## Evidence and privacy

- Store the report in the private document store; record its reference and a
  SHA-256. Do not paste the report body or the standard text into OpenGRC.
- A SOC 2 report contains sensitive control descriptions. Keep access scoped like
  any other evidence.
- A vendor report belongs to the vendor relationship; do not publish it in the
  trust center unless the vendor permits it.
- The register is not a substitute for reading the report. A summary field is a
  pointer, not the opinion.

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| Expired certificate cited in a questionnaire | No expiry or review date | Register the expiry; run the command |
| "SOC 2 passed" with no scope | The report is treated as a badge | Record the system, period and opinion |
| Qualified opinion summarised as clean | The exceptions were not read | Record the exceptions and raise a finding |
| Bridge letter missing | The period gap was not tracked | Record the bridge letter date |
| Vendor report in a shared drive | No internal register | Add the vendor link and owner |
| Report body copied into the GRC database | The document boundary was ignored | Keep the report in the private store; record the reference and hash |

## Limits

- No automatic parsing of report PDFs; the opinion and exceptions need a reader.
- No separate framework import for SOC 1/3; they are report types.
- No trust-center publishing workflow; the register is internal.
- No automatic vendor follow-up; the vendor record and the review date are the
  controls.
