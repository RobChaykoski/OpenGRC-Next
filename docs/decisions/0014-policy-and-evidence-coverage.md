# 0014 — Policies link directly to outcomes; evidence inherits through its control

Status: accepted
Date: 2026-11-18

## Context

Milestone A.3 has to answer "what proves this outcome?" from three objects:
controls, policies/documents, and evidence. Controls already have curated
many-to-many links (ADR 0013). The remaining question is how the other two join.

Evidence is append-only: its hash, payload, and chain of custody are the point
(ADR 0008). Adding a second editable link on the evidence record would either
mutate ledger metadata after the fact or create a parallel mapping that can
disagree with the control the evidence already names.

Policies are local records. A policy often supports an outcome directly even when
no imported clause covers it — an information security policy is not "AC-2".

## Decision

1. `policy_canonical_outcome` is a direct many-to-many link, made by people in the
   UI. Policies are not imported reference data, so there is no curated config
   map for them yet.
2. Evidence is not linked a second time. It inherits outcomes through
   `evidence_records.control_id` and that control's curated outcome links.
   Evidence with no control therefore has no outcome, which is an honest gap.
3. Coverage counts, per outcome:
   - controls: distinct linked controls, excluding soft-deleted ones;
   - policies: distinct policies linked directly **or** through a linked
     control, deduplicated, excluding soft-deleted policies;
   - evidence: distinct evidence records whose control is linked, excluding
     records whose control is soft-deleted.
4. The UI states the rule where it applies: the policy form explains direct and
   indirect support, the policy view marks an unlinked policy as a coverage gap,
   and the evidence view labels outcomes as inherited from the control.

## Consequences

- One provenance path for evidence. Nothing re-points a ledger record to make a
  report look better.
- A policy can support an outcome without inventing a framework clause for it.
- Evidence cannot support an outcome that no control is linked to. That is
  deliberate: link the relevant control to the outcome, or accept the gap.
- Coverage is a de-duplicated count, not a sum of links. Two paths to the same
  policy are still one policy.
- Curated policy mappings will need a separate design if a document register is
  ever shipped; the `source` column is reserved for that.
