# 0049 — Access governance: policy, decision and just-in-time grant

- Status: accepted
- Date: 2026-11-21

## Context

Organisations need to show *why* a person or service was allowed to see
sensitive data, at a particular moment, under particular conditions — and to
show it for emergency access as clearly as for routine access. The platform
already holds data classification, the risk register, evidence sources and
periodic access reviews, but it had no record of a runtime access decision, no
time-bound grant and no place to record the privacy review that a
break-the-glass event requires.

The tempting design is to put the policy engine in the query path (Open
Policy Agent in front of a warehouse, or a token claim that the enforcer
trusts). That design was considered and rejected as the *default* for this
product: OpenGRC is a system of record. It is not a policy enforcement point,
it does not mint tokens, and it does not sit between a clinician and a record.

## Decision

OpenGRC owns the **governance objects** of real-time and just-in-time access:

1. **`ProtectedResource`** — the data side. A dataset, table, column set, record
   scope or API, with an organisation-wide classification level
   (`DocumentClassification`), an optional consent directive (lockbox / opt-out),
   an owner, and a classification timestamp and version.
2. **`AccessPolicy`** — the rule. Classification level × accessor profile ×
   context conditions → an action (`allow`, `allow_audited`, `mask`, `filter`,
   `step_up`, `time_boxed`, `deny`), with a priority, dry-run flag, owner and
   review date. The rule set is hashed so a decision can be reproduced.
3. **`AccessDecision`** — append-only evidence. What was requested, the
   classification snapshot, the asserted context, the matched policy and
   version, the action, the TTL, the exposure weight, and a hash chain.
4. **`AccessGrant`** — just-in-time access. Subject, resource scope, purpose,
   justification, approver, start, expiry, state, and — for break-the-glass —
   the clinical reason and the mandatory privacy review.
5. **Exposure** — a computed weight of sensitive data consumed per subject in a
   rolling window. It is derived from decisions, never stored separately, so it
   cannot drift from the log.

### Precedence and failure behaviour

Evaluation is deterministic and ordered:

1. explicit deny (no matching policy, stale classification, exposure ceiling)
2. break-glass (requires a justification, an emergency profile *and* high
   context trust)
3. an active grant
4. matched policy by priority, then specificity
5. default deny

The evaluator never returns "allow" because it could not decide. An unknown
classification, a stale classification, a missing policy or an unknown context
value masks or denies. If the enforcement point receives no decision, it must
treat that as deny — this is part of the integration contract, not a setting.

### Break-the-glass rules

- A grant requires a written justification and, for clinical access, a reason
  code from the configured vocabulary.
- Break-glass is refused when context trust is not high (off-site, unmanaged
  device). The refusal is a decision record, not a silent error.
- The TTL is capped by `access_governance.grants.break_glass_max_minutes`. TTL
  is a backstop: revocation is event-driven (patient stabilised, encounter
  closed, transfer), and expiry is what happens when the event never arrives.
- A break-glass grant always creates a privacy review obligation with an owner
  and a due date. An alert is not a review.
- A consent directive is recorded as a directive, not a deny bit. Whether it
  can be overridden in an emergency is a legal question for counsel; the
  product records the override and the review that follows.

### Separation of duties

The approver of a grant cannot be its requester. Privileged and machine
subjects are the highest-value targets, so their grants also require a review
date.

## Consequences

- The enforcement point is out of scope. The contract is: submit the request
  context, receive an action and a correlation id, honour `deny`/`mask` on
  *no decision*. An OPA/Trino deployment can consume the policy table; it does
  not become the source of truth for classification.
- Decisions are append-only at the model layer and hash-chained. Concurrent
  writers must be serialised by the database; the chain detects alteration and
  ordering, not intent.
- The context snapshot is deliberately narrow: profile, trust level, purpose,
  facility/zone label, and the time. Raw IP addresses, device identifiers and
  free-text clinical notes do not belong in a governance record. Store a zone
  or a hash, with the reason recorded next to the policy.
- The classification timestamp matters as much as the level. A stale tag is a
  coverage gap and is reported as one, not as a clean result.
- Access reviews (ADR 0033) remain the periodic attestation of entitlements;
  grants are continuous and time-bound. They answer different questions and
  link through the privacy review, not by merging the tables.

## Alternatives considered

- **OPA in the query path with OpenGRC as the decision service.** Rejected as
  the default: it makes availability of the record system a condition of
  clinical access, and it puts personal data in the decision path. It remains a
  supported integration.
- **Reusing `ApprovalRequest` for grant approval.** Rejected: an approval is a
  workflow step, a grant is an entitlement with a clock and a privacy
  obligation. The grant links to the approval record, it does not replace it.
- **Reusing `AccessReview` for privacy review.** Rejected: a campaign reviews
  every account on a cycle; a privacy review is attached to one event.

## References

- `docs/grc/access-governance.md` — operating guide
- `docs/security/tenant-and-access-roadmap.md` — attribute-based access and the
  immutable audit subsystem this work feeds
- `docs/grc/data-posture.md` — DSPM coverage and classification freshness
- NIST SP 800-207 — policy decision separated from enforcement
