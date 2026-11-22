# 0052 — A break-glass decision mints the grant and its review

- Status: accepted
- Date: 2026-11-21

## Context

ADR 0051 shipped the decision endpoint and left one gap written down: a
break-glass answer returns `time_boxed` with a TTL, but the endpoint does not
create an `AccessGrant`. The decision records what was allowed; the grant is the
entitlement with a clock and the privacy review obligation that ADR 0049 says
must follow every break-glass event.

Leaving them apart produced three problems:

1. The privacy review was nobody's record. An auditor could see that emergency
   access happened and could not see the review that was supposed to follow.
2. The next request created another `time_boxed` answer, because no grant
   existed, so volume and TTL were never tracked as an entitlement.
3. "The grant is recorded by the workflow" was documentation, not behaviour.

## Decision

A break-glass decision and its grant are one write.

1. **One transaction.** When the evaluator returns `time_boxed` with reason
   `break_glass`, the endpoint creates the `AccessGrant` and the `AccessDecision`
   in a single database transaction. A retry that loses the idempotency race
   rolls back both; no orphan grant survives.
2. **The grant is active immediately.** `state = active`,
   `requested_by` = the token's service account, `approved_by` = null,
   `starts_at` = now, `expires_at` = now + the evaluator's capped TTL,
   `is_break_glass = true`, `clinical_reason` from the request,
   `review_required = true`, `review_outcome = pending`,
   `review_due_at` = today + `break_glass_review_days` (default 5).
   There is no approver because emergency access cannot wait for one; the
   privacy review is the check that follows, and an overdue review is reported.
3. **A written justification is required.** The request gains an optional
   `justification` text field (max 1,000 characters). `justification_present`
   still gates policy conditions, but the text is what the grant records. A
   break-glass answer without it is recorded as `deny` with reason
   `context_insufficient`, and no grant is created: fail closed rather than grant
   access whose review nobody can complete.
4. **The records are linked.** `access_decisions.access_grant_id` points at the
   grant, both rows share the `correlation_id`, and the response carries
   `grant_reference` (`AG-00042`). A replay returns the same reference without
   appending anything.
5. **Privacy.** The justification is free text written by a professional and
   will sometimes contain personal data. It is stored on the grant only, under
   the access rules that already govern grants. It never enters the decision
   context snapshot, a log, an export, an audit pack or an AI prompt.
6. **Only break-glass mints.** A policy-driven `time_boxed` answer
   (`reason: policy_match`) is still honoured by the enforcement point with the
   returned TTL. It creates no entitlement and no review obligation. Unifying the
   two is deliberate future work, not an accident.

## Consequences

- Every break-glass answer now has a grant, a review due date and a row in the
  grant register. The "overdue privacy reviews" metric has something to count.
- A break-glass grant can be revoked like any other grant, and the expiry command
  already gives it a fail-safe end.
- The endpoint now writes two rows per break-glass decision, in one transaction.
  The extra cost is one insert on an emergency path, not a per-request cost.
- The response gains `grant_reference`. It is additive; a client that ignores it
  still gets the same decision.
- The justification is the first free-text field the decision endpoint accepts.
  That is a deliberate exception with a cap, a single storage location and an
  explicit privacy rule. It must not become a general-purpose notes field.
- A grant created this way has no approver, so it cannot satisfy a control that
  requires prior approval. It satisfies the break-glass control, which requires
  after-the-fact review.

## Alternatives considered

- **Return `time_boxed` and let the operator create the grant later.** Rejected:
  that is the gap this ADR closes, and the review obligation was the part most
  likely to be missed.
- **Create the grant in `requested` state and require approval.** Rejected: an
  emergency cannot wait for an approver. The separation-of-duties rule still
  applies to ordinary grants.
- **Require a system approver account.** Rejected: it turns a human review into a
  rubber stamp and makes the reviewer column lie.
- **Store the justification in the decision context as well.** Rejected: the
  context snapshot is deliberately narrow and is read by dashboards and exports.
- **Accept `justification_present` alone and store a placeholder.** Rejected: a
  grant whose justification says "present" is evidence of nothing.

## References

- `docs/grc/access-governance.md` — contract and operating guide
- ADR 0049 — access governance objects and break-glass rules
- ADR 0051 — the decision endpoint
- ADR 0031 — risk acceptance is a dated decision (same pattern: the record is the
  control)
