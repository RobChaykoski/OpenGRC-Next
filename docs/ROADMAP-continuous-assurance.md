# Continuous assurance roadmap

OpenGRC's category is **open-source continuous assurance**: turn operational
security data into audit-ready evidence, rather than digitising spreadsheets.

This page records what already exists, what is missing, and the order to build it
in. It is a plan, not a promise. Decisions with trade-offs belong in
`docs/decisions/`.

## Where the code already is

| Capability | Status | Where |
|---|---|---|
| Evidence ledger (append-only, hashed, chained) | Built | `evidence_records`, `EvidenceIngestor`, `opengrc:evidence:verify` |
| Evidence sources and collection jobs | Built | `evidence_sources`, `evidence_jobs`, `CollectEvidence` |
| Discovery evidence ingestion API | Built | `POST /api/v1/evidence`, scoped client-bound token |
| Continuous control validation | Built | `control_validation_rules`, `control_validations` |
| Automated findings from failed validation | Built | `findings` opened/updated/resolved with owners and due dates |
| Framework scoping from an organisation profile | Built (CLI) | `opengrc:scope profile.json`, `FrameworkScopingService` |
| Unified control catalogue | Built as data; links pending | `canonical_controls`, `canonical_frameworks`, `opengrc:catalog:sync` (ADR 0012) |
| Curated control-to-outcome links | Built | `control_canonical_outcome`, `opengrc:catalog:link` (ADR 0013) |
| Policy and evidence coverage | Built | `policy_canonical_outcome`, `CanonicalOutcomeCoverage` (ADR 0014) |
| Evidence confidence and assurance score | Built | `AssuranceScore`, `assurance_snapshots`, `opengrc:assurance:snapshot` (ADR 0015) |
| Control matrix UI | Built | Foundations → Assurance matrix (`FrameworkMatrix`) |
| Evidence confidence | Missing | — |
| Assurance score and history | Missing | — |
| Digital auditor (effectiveness judgement) | Missing | — |
| Internal AI assistant over the graph | Missing | MCP was removed (ADR 0006) |
| Role model | Three roles | `docs/decisions/0007` |

The important gap: the canonical catalogue exists as configuration, not as data.
Nothing joins it to controls, implementations, evidence, risks, or documents. That
join is the product.

## Order of work

### Milestone A — persist the control graph (highest leverage)

Everything else reads from this.

1. ~~Tables for canonical controls and framework mappings.~~ Done:
   `canonical_controls`, `canonical_frameworks`, `canonical_control_framework`,
   seeded idempotently by `opengrc:catalog:sync` (ADR 0012).
2. ~~Link existing `controls` to canonical controls.~~ Done:
   `control_canonical_outcome` is many-to-many and holds curated links only;
   `opengrc:catalog:link` applies the reviewed map, the UI lets people link a
   control, and unlinked controls are labelled as coverage gaps (ADR 0013,
   `docs/grc/control-catalogue-linking.md`).
3. ~~Link policies/documents and evidence records to canonical controls.~~ Done:
   policies link directly in the UI; evidence inherits outcomes through its
   control, so the ledger is never re-pointed. `CanonicalOutcomeCoverage` returns
   de-duplicated counts (ADR 0014).
4. ~~Read-only matrix page: canonical control × framework, with category, evidence
   expectation, automation level, and a link back to the internal control.~~ Done:
   Foundations → Assurance matrix shows required outcomes, linked controls,
   policies, evidence and a gaps filter. It reports review state, never
   compliance.

Acceptance: a user can pick a framework and see every canonical control it
requires, which internal controls satisfy it, and whether evidence exists.

Milestone A is complete. Milestone B (confidence, coverage and the assurance
score) builds on these counts.

### Milestone B — confidence and coverage

1. ~~Evidence confidence derived from the source, not typed in by a user:
   documentation 20, manual upload 50, API-collected 80, real-time validation
   100.~~ Done: `CanonicalOutcomeCoverage` derives the best basis per outcome.
2. ~~Automation coverage: share of controls whose evidence arrives without a
   human.~~ Done: the automation component counts API or validation evidence.
3. ~~Monitoring coverage: share of controls with an enabled validation rule.~~
   Done: the monitoring component.
4. ~~Assurance score v1: weighted coverage, evidence quality, automation, and
   monitoring — with the components visible, never a single unexplained
   number.~~ Done: `AssuranceScore`, weights 25/30/20/15/10 (ADR 0015).
5. ~~Snapshot the score periodically so it has a trend.~~ Done:
   `opengrc:assurance:snapshot` daily, one row per framework per day; the page
   shows a 30-day delta.

Milestone B is complete. Milestone C (audit readiness) is next.

### Milestone C — audit readiness

1. Evidence pack export per framework: control → implementation → evidence →
   validation results → findings.
2. Gap report: controls with no evidence, stale evidence, or failing validation.
3. Auditor portal scoped to an audit, read-only, with an access log.

### Milestone D — assisted reasoning

1. Internal assistant service (server-side, permission-scoped). Do not expose the
   whole data model to a third-party client by default; that was the MCP failure
   mode removed in ADR 0006.
2. Read-only questions first ("which PHIPA controls lack evidence?"), then
   suggestions with rationale, then human-approved writes.
3. Prompt-injection controls: retrieved content is untrusted, tool output is not
   instructions, every action is logged with actor and arguments.

## What not to do

- **Do not build ten layers in parallel.** The catalogue join is the dependency
  for the matrix, confidence, score, and assistant.
- **Do not store a user-editable confidence score.** It becomes a vanity metric.
  Derive it from how the evidence arrived.
- **Do not call framework coverage "compliance".** Assurance is evidence quality
  and monitoring, not a percentage of checkboxes.
- **Do not reintroduce an open MCP endpoint.** If external assistants return, they
  return scoped, authenticated, audited, and with explicit approval for writes.
- **Do not add permission strings back.** Three roles plus explicit scopes on
  machine tokens; see ADR 0007.

## Measures

- Time from a discovery scan to audit-ready evidence.
- Percentage of controls with machine-collected evidence.
- Percentage of controls with an active validation rule.
- Evidence age distribution per framework.
- Findings opened automatically that are resolved without a human chasing.
- Assurance score trend, with component breakdown.

## Current integration contract

`docs/integrations/discovery-evidence.md` documents the token, payload, replay
semantics, and retry classification. OpenDLP-NG dispatches to
`POST /api/v1/evidence`; failed writes are classified as retryable or permanent
instead of looping forever.
