# 0053 — DLP policies and suppressions are governance records with an expiry

- Status: accepted
- Date: 2026-11-21

## Context

The handoff recorded three DLP gaps: there was no first-class policy object, no
suppression workflow with an expiry, and no link from a finding to a risk or a
control. The quality page measured false positives; nothing recorded what the
programme had decided to protect, what it had chosen to accept, or why.

The tempting design is to make OpenGRC the DLP console: push policy rules into
the scanning engine and let a suppression change what the engine reports. That
would put OpenGRC in the detection path, make an outage a detection outage, and
turn a governance record into executable configuration.

## Decision

OpenGRC records the programme; the scanning engine enforces it.

1. **`DlpPolicy` is a governance record.** Code, name, description, channels
   (email, endpoint, cloud, web, AI prompt, upload), data classes, identifier
   types, action intent (monitor, alert, block, quarantine), owner, review date,
   active flag. It is not executable, it is not pushed to the engine, and it has
   no detector code. An overdue policy is reported, not trusted.
2. **`DlpSuppression` is a time-boxed acceptance.** Scoped to one policy and,
   optionally, one identifier type and one match label. It requires a written
   justification and an expiry in the future, capped at 365 days. It is requested,
   approved by someone other than the requester, revoked with a reason, or
   expired by `opengrc:dlp:expire-suppressions` (daily). Expired is terminal;
   continuing the acceptance is a new request.
3. **A suppression never hides a finding.** The finding stays in the queue with
   the suppression visible. The suppression is a decision someone made, not an
   absence of data, and deleting findings to tidy a queue destroys the record the
   programme is built on.
4. **Findings link to policy, risk and control.** All three references are
   nullable. A finding with no policy is a coverage gap and is shown as
   unassigned; a risk or control that is removed clears the link and keeps the
   finding. The link is a reference for audit trail, not a claim of causation.
5. **No engine integration in this decision.** The scanner does not read these
   tables. A suppression does not change what Presidio, OpenDLP or a vendor
   console reports; it records that the organisation has accepted the output for
   a scope and a period. Pushing rules into an engine is a separate decision with
   its own failure behaviour.
6. **Privacy.** A suppression stores a justification and a scope. It stores no
   matched value, no content and no detector payload. The finding still holds
   only metadata and a content hash.

## Consequences

- The false-positive rate can be read by policy and identifier type, because
  findings now carry a policy link.
- An auditor can follow finding → policy → risk/control → framework, which was
  manual before.
- A suppression is evidence of an acceptance with an owner, an approver, a date
  and a reason. That is the shape an exception should have (ADR 0024), applied to
  detector output rather than to a policy requirement.
- The queue still needs a human. Suppressions do not reduce review volume
  automatically; they record what has been accepted.
- Nothing measures recall, and nothing performs exact-data-match. The quality
  page is still a false-positive measure with programme targets (ADR 0040).
- A policy can be soft-deleted while its findings remain, which keeps the history
  and leaves those findings unassigned. A hard delete would break the link; the
  UI uses soft deletes.

## Alternatives considered

- **Reuse `PolicyException`.** Rejected: a policy exception deviates from a
  requirement; a suppression accepts a detector's output. They have different
  owners, scopes and expiry semantics, and merging them would make both reports
  wrong.
- **Make suppressions permanent with a review note.** Rejected: "temporary" is
  how permanent exceptions start, and the handoff asked for expiry.
- **Push suppression state to the scanning engine.** Deferred, not rejected: it
  needs a contract, an idempotency story and an outage behaviour. A governance
  record that the engine ignores is honest; a half-implemented push is not.
- **Delete suppressed findings.** Rejected: it destroys the denominator for the
  quality measure and the evidence that an acceptance happened.

## References

- `docs/security/data-loss-prevention.md` — operating guide
- `docs/security/dlp-quality.md` — false-positive targets and review loop
- ADR 0040 — DLP quality targets are programme targets
- ADR 0024 — exceptions are scoped, approved and expire by default
- ADR 0008 — append-only evidence and hash chains
