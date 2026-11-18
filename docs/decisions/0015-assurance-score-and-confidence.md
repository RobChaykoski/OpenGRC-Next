# 0015 — Assurance score is component-visible and confidence is derived

Status: accepted
Date: 2026-11-18

## Context

Milestone B needs a number a programme can track. The easy version is a single
percentage that invites "are we compliant?" and cannot be explained when it
moves. The roadmap already forbids two failure modes: a user-editable confidence
score, which becomes a vanity metric, and a score shown without its components.

Evidence arrives in different ways, and those ways are not equally strong.
Documentation says an intention exists. A manual upload says someone supplied a
file. An API measurement says a system reported a fact. A validation result says
a deterministic rule evaluated a measurement. Those are ordered, not equivalent.

## Decision

1. Confidence is derived per outcome from the best basis available:
   - validation result — 100
   - API-collected or connector evidence — 80
   - manual or unknown evidence record — 50
   - documentation only (a linked policy/document) — 20
   - nothing — 0
2. The classification comes from how the record arrived, not from a field a user
   edits. A record counts as machine-collected when its source is a connector, or
   when it carries a discovery `client_id`.
3. The score has five visible components, weighted to 100:

   | Component | Weight | Meaning |
   |---|---|---|
   | Reviewed | 25 | Share of required outcomes with a linked control |
   | Evidence | 30 | Share with an evidence record or validation result |
   | Quality | 20 | Average best confidence among evidenced outcomes |
   | Automation | 15 | Share whose best evidence is API or validation |
   | Monitoring | 10 | Share with an enabled validation rule on a linked control |

4. There is no score for a framework with no outcomes, and no cross-framework
   total. A number that averages unrelated frameworks hides which one is behind.
5. `opengrc:assurance:snapshot` stores one row per framework per day, including
   the components and counts. Re-running it on the same day updates that row.
   The page shows a 30-day delta when at least two snapshots exist.

## Consequences

- A move in the score can be attributed to a component and to specific outcomes.
- An outcome supported only by a policy is capped at 20 confidence; evidence of
  operation is what raises it.
- The weights are a product judgement. Changing them is a deliberate change with
  a test, not a configuration a user can tune per report.
- Trend data starts when the command first runs; the page says so instead of
  inventing history.
- The score is still not compliance. It measures review, evidence, automation
  and monitoring. An auditor's opinion remains an auditor's opinion.
