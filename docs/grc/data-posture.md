# Data posture (DLP and DSPM)

The **Data posture** page (app panel → Discovery → Data posture) is the teaching
dashboard for two related but different jobs:

- **DLP** controls data at the point of use: uploads, endpoints, email, and AI
  prompts. It measures blocks, flags, false positives, and response time.
- **DSPM** controls data at rest: where sensitive data lives, who can reach it,
  whether it is protected, who owns it, and whether it should still exist. It
  measures coverage, classification, exposure, ownership, and retention.

## What the page shows

| Section | Question it answers |
|---|---|
| Posture cards | How many sensitive findings are open, how much of the estate is classified, and how fresh is the mirror? |
| Catalog governance | Are the datasets in the catalog owned and classified? (OpenMetadata evidence) |
| Category chart | Where sensitive data is concentrated: PII, PHI, cardholder data, secrets, credentials |
| Aging chart | Are open findings getting older? Aging is the programme problem, not a detector problem |
| Exposed assets | Which assets concentrate the most open findings |
| DLP operations | What did policy block or flag in the last 7 days? |
| Employer lens | The questions a hiring manager asks about data risk and evidence |

## Data sources

- OpenDiscoveryEngine scan and finding mirror (`discovery_scans`,
  `discovery_findings`) for DSPM.
- OpenGRC `DlpFinding` rows for AI and upload DLP.
- OpenMetadata `catalog-governance` evidence for dataset ownership and
  classification coverage.
- The OpenGRC asset count as the coverage denominator.

Only aggregates are rendered. File paths, match previews, and hashes stay on the
finding detail page, behind the read-only console policy. The dashboard never
shows raw content.

## How to read it

- **Unknown is not clean.** A store that was never scanned is a coverage gap,
  not a zero.
- **Coverage precedes findings.** A low finding count with low coverage is an
  unmeasured programme.
- **Freshness precedes status.** A stale mirror describes last month, not today.
- **Aging precedes severity.** A critical finding that has been open for 90 days
  is a process failure, not just a technical one.

## What it does not do

- It does not scan, classify, or remediate.
- It does not replace NetBox, Ralph, or Fleet asset data.
- It does not prove a clean estate; it shows where the evidence is thin.
- It does not make a compliance decision; it points to the control evidence that
  does.

## Operating it

1. Configure an OpenDiscoveryEngine console source (`DISCOVERY_CONSOLE_*`).
2. Run `php artisan opengrc:discovery:sync`.
3. Review the page, then open the discovery findings and the assurance matrix.
4. Use the recommendations to assign an owner, restrict access, retire stale
   data, or add a validation rule.

The page is read-only for every role. It does not create, edit, or delete a
finding.
