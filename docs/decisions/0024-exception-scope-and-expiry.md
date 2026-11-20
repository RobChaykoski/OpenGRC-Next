# 0024 — Exceptions are scoped, approved and expire by default

Status: accepted
Date: 2026-11-20

## Context

OpenGRC already records policy exceptions: justification, risk assessment,
compensating controls, requester, approver, dates and status. But an exception
lived only inside the policy that owned it. There was no cross-policy register for
an approver or auditor, no scheduled expiry, and an approved exception with a
past date still read as approved until someone edited it.

That is the difference between a field and a control. An exception is a formal
decision to accept a deviation. It needs a named requirement, a reason, a risk
assessment, compensating controls, a second person, and an end date. The register
is evidence that the decision was governed, not a list of free-text notes.

Risk acceptance is related but not the same thing: accepting a risk is a
treatment decision, while a policy exception waives a specific requirement. They
must not collapse into one generic "exception" record.

## Decision

1. **An exception is scoped to the requirement it waives.** Phase 1 is policy
   exceptions. Control, vendor, asset and risk exceptions are later, separate
   scopes with their own subject; a generic unattached exception is not allowed.
2. **Required fields are part of the decision.** Justification, risk assessment,
   compensating controls, requester, approver, effective date and expiry are not
   optional prose. Approval requires a named approver and a dated end.
3. **No self-approval.** The approver must be a different person from the
   requester. A one-person approval is a note, not a control.
4. **Time-bound by default.** An approved exception needs an expiration date.
   A far-future date is still a review date; an open-ended exception is the
   exception, and needs an explicit programme decision outside the product.
5. **Expiry is enforced, not decorative.** `opengrc:exceptions:expire` marks an
   approved exception past its expiry as Expired every day. It does not renew,
   extend or re-approve anything. Re-approval is a new decision with a new date.
6. **Revocation is a status, not a delete.** Revoked and expired records stay
   readable. Soft deletion is reserved for a correction, and the audit trail
   remains.
7. **The register is global and read-only.** Approvers and auditors see every
   exception, its policy, status, expiry and compensating controls in one place.
   Creation and editing stay on the policy, where the requirement lives.
8. **A missing exception is not a pass.** An expired, revoked or unapproved
   exception is a gap, not a silent waiver. The policy and control views must
   show the state.
9. **Risk acceptance gets its own record.** It is a treatment decision with an
   owner, an approver, a rationale and an expiry, not a policy exception. The
   risk register will carry it explicitly rather than hiding it in a mitigation
   strategy.

## Consequences

- Approval becomes slower on day one and defensible later. A deviation with no
  end date is no longer the default.
- The register gives an auditor one screen for every waiver, its policy and its
  compensating controls.
- The daily expiry command is a small operational dependency. If the scheduler
  stops, expired exceptions read as approved; doctor or monitoring should treat a
  stale scheduler as a gap.
- Existing permanent exceptions need a review when next edited: either a dated
  expiry or an explicit programme decision.
- A policy exception is not a risk acceptance. A programme that uses one for the
  other will have an incomplete risk record.

## Alternatives considered

- **Leave expiry as a manual field.** Rejected: the status then describes what
  someone last typed, not whether the waiver is valid today.
- **Auto-renew on expiry.** Rejected: a silent roll-forward removes the decision
  and defeats the control.
- **Delete expired exceptions.** Rejected: the audit trail and the reason for the
  deviation disappear.
- **One generic polymorphic exception table for everything.** Rejected as a first
  step: a policy exception, a control exception and a risk acceptance answer
  different questions and carry different evidence. Generalise deliberately when
  the second real use case exists.
- **Put exceptions only in the policy screen.** Rejected: no cross-policy review,
  no expiry queue, and no evidence a reviewer can inspect without opening every
  policy.
