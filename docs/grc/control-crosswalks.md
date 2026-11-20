# Control crosswalks

A crosswalk records a reviewed mapping between two imported controls from
different frameworks. It answers "which requirement here corresponds to that
requirement there?" with a rationale, a confidence and an approver.

See ADR 0042. A crosswalk is not textual equivalence and not evidence.

## When to use one

Use a crosswalk when:

- a canonical outcome is too coarse for the mapping you need;
- a reviewer needs to record why two specific requirements overlap;
- an auditor asks for a defensible cross-framework mapping, not a spreadsheet.

Use the canonical outcome catalogue instead when the common outcome already
captures the relationship. That is the preferred path because it de-duplicates
coverage across many frameworks.

## What it is not

- Not a claim that the two texts are identical.
- Not a claim that meeting one automatically meets the other.
- Not evidence that either control operates.
- Not a certification or legal opinion.
- Not permanent. A revised standard requires a new review.

## Creating a crosswalk

1. Open **Entities → Control crosswalks**.
2. Choose a source control and a target control from different standards.
3. Write the rationale: why do these two requirements serve the same programme
   intent? Point at the text.
4. Choose confidence: high, medium or low.
5. Save as a draft.

The same pair cannot be recorded twice, in either direction. A control cannot be
crosswalked to itself, and two controls from the same standard are rejected.

## Review

Only an admin can approve or reject:

- **Approve** records the reviewer, the time and an optional note.
- **Reject** requires a reason.

An approved or rejected mapping is immutable in the UI. To change the judgement,
create a new record after review; do not rewrite history.

## Reading the register

| Column | Meaning |
|---|---|
| Source / target | The two imported controls and their frameworks |
| Confidence | The reviewer's judgement, not a score |
| Status | Draft, approved or rejected |
| Rationale | Why the mapping exists |
| Review note | The approval or rejection reason |
| Reviewer / reviewed | Who decided and when |

Filters include status, confidence and source/target framework.

## Relationship to canonical outcomes

The preferred model is:

```text
imported control A ─┐
                    ├── canonical outcome ── programme intent
imported control B ─┘
```

A crosswalk is the narrower model:

```text
imported control A ── reviewed mapping ── imported control B
```

Use the narrow model only when the shared outcome is too coarse or the pair needs
its own rationale. A crosswalk does not replace the outcome catalogue and does not
change coverage totals in the assurance matrix.

## Evidence and review checklist

Before approving:

- [ ] Both controls are from different standards.
- [ ] The rationale points at the requirement text, not the code or title.
- [ ] The mapping is not presented as textual equivalence.
- [ ] The confidence reflects the reviewer's certainty.
- [ ] The pair is not already covered by a canonical outcome that would do the job.
- [ ] Any affected coverage or gap report is understood; a crosswalk alone does not
      close an outcome gap.

## Limits

- No automatic matching or similarity score.
- No bulk import of crosswalks.
- No deletion of approved mappings.
- No effect on assurance scores or evidence status.
- No multi-step approval; one admin decision is the control.
