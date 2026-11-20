# 0042 — A control crosswalk is a reviewed mapping, not equivalence

Status: accepted
Date: 2026-11-20

## Context

Programmes often need to answer "which of our ISO controls satisfy this NIST
requirement?" The canonical outcome catalogue is the preferred bridge: both
requirements link to the same outcome, and coverage de-duplicates them. But an
outcome can be too coarse for a specific mapping, and some programmes need an
explicit pair with a rationale and an approver.

Without a place to record that pair, it lives in a spreadsheet with no reviewer,
no confidence and no history.

## Decision

1. **A crosswalk is a pair of imported controls from different standards.** The
   same control, or two controls from the same standard, is not a crosswalk.
2. **Every crosswalk has a rationale.** It must explain why the two requirements
   serve the same programme intent. A code prefix or a similar title is not a
   rationale.
3. **Confidence is explicit.** High, medium or low. Confidence is a reviewer's
   judgement, not a computed score.
4. **Draft until approved.** Editors can draft; only an admin can approve or
   reject. Approval records the reviewer, the time and an optional note. A
   rejection requires a reason.
5. **One pair, one record.** The same pair cannot be recorded twice, in either
   direction. A revision is a new review of the same record, not a duplicate.
6. **An approved mapping is immutable in the UI.** To change it, reject or
   replace it through a new review. The history is the audit trail.
7. **A crosswalk is not equivalence.** It does not say the texts are identical or
   that meeting one automatically meets the other. It says a reviewer judged the
   intent to overlap.
8. **A crosswalk is not evidence.** Evidence and validation still decide whether
   a control operates. A mapping does not close a gap or prove compliance.
9. **Canonical outcomes remain preferred.** Use a crosswalk when the outcome is
   too coarse or the pair needs its own rationale. Do not use a crosswalk to avoid
   curating the outcome catalogue.

## Consequences

- The programme can show a reviewed mapping, who approved it and why, instead of
  an unexplained spreadsheet cell.
- Direct pairs can multiply. A large set of pairwise mappings is harder to review
  than a shared outcome; the canonical catalogue should carry the common case.
- Approval separation adds a step. That is intentional: a mapping is a judgement
  that affects coverage claims.
- Confidence is subjective and will drift between reviewers. The rationale is
  the durable part; review it, not only the label.

## Alternatives considered

- **Free-text crosswalk notes on a control.** Rejected: no target, no reviewer,
  no duplicate control and no report.
- **Automatically match controls by title or code.** Rejected: hides wrong
  mappings behind a similarity score.
- **Treat a crosswalk as evidence.** Rejected: a mapping is a claim about intent,
  not proof that either control operates.
- **Only use canonical outcomes.** Rejected: useful for the common case, but some
  pairs need an explicit reviewed rationale.
- **Allow editors to approve.** Rejected: approval is the control that keeps the
  mapping from becoming a personal opinion.
