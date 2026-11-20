# 0038 — CSET is an external assessment; import the scrubbed export only

Status: accepted
Date: 2026-11-20

## Context

CSET (Cyber Security Evaluation Tool, CISA and Idaho National Laboratory) guides
an assessor through standards and maturity models, records answers, and produces
observations and recommendations. It is distributed under the MIT and Apache 2.0
licences. It runs as a standalone Windows application or an enterprise
deployment; OpenGRC should not become a second CSET.

A CSET assessment export can contain **Protected Critical Infrastructure
Information (PCII)**: organisation names, sites, contacts, free-text comments and
feedback. CSET can scrub that data on export, and the scrubbed export is the
appropriate input for a GRC system.

## Decision

1. **No CSET runtime or question bank in OpenGRC.** CSET stays a separate tool.
   OpenGRC does not reproduce its standards, maturity models or scoring.
2. **Import the PCII-scrubbed JSON export.** The importer accepts CSET's JSON
   export. It rejects the encrypted `.cset` archive; decrypt and export the
   scrubbed JSON first.
3. **Read only what the register needs.** The importer reads the assessment name,
   date, SAL level, selected standards and maturity models, answer counts, and
   observations. It ignores contacts, organisation and facility details, CIS
   demographics, comments, feedback and question/requirement text.
4. **Observations become findings.** Each unique CSET observation becomes one
   finding, deduplicated by assessment GUID and observation id. CSET importance
   maps to critical/high/medium/low; a missing importance is medium.
5. **Answers are counts, not findings.** A negative answer is recorded in the
   report metadata so coverage is visible. It does not create a finding on its
   own; CSET's observation is the actionable statement.
6. **A CSET result is not a control verdict.** The finding still needs triage,
   an owner, and a decision about the control. The report is evidence, not a
   compliance certificate.
7. **The raw file stays outside OpenGRC.** The SHA-256, source metadata and
   findings are kept. Keep the export in the document store under the same
   retention as the assessment.
8. **No automatic scoring or maturity claim.** OpenGRC does not recompute CSET's
   score or claim a maturity level from the imported answers.

## Consequences

- An assessor can keep using CSET and hand the programme a reviewable finding
  queue without a second console.
- The importer is deliberately narrow. A CSET version that changes the export
  shape needs a review and a fixture, not a silent best-effort parse.
- The PCII boundary depends on the exporter choosing the scrubbed export. The
  importer does not guess which fields are sensitive; it ignores the known
  sensitive blocks and documents the requirement.
- Observations may still contain sensitive detail if the export was not scrubbed.
  Treat the imported findings as internal evidence and review before sharing.
- Answer counts are useful for coverage and trend, but they are not a score.

## Alternatives considered

- **Run CSET inside OpenGRC.** Rejected: a Windows/.NET runtime, a second
  question bank and a duplicate assessment UI.
- **Import the encrypted `.cset` archive.** Rejected: it needs a password and can
  contain PCII; the scrubbed JSON is the supported hand-off.
- **Store every answer and comment.** Rejected: personal data, PCII and a large
  duplicate of the CSET database.
- **Create a finding for every negative answer.** Rejected: most negative answers
  have no recommendation; CSET's observations are the considered output.
- **Recompute the CSET score.** Rejected: the score is CSET's methodology, not
  OpenGRC's, and the inputs include questions OpenGRC does not store.
