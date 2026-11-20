# 0041 — Control owner attestation is a dated statement, not a control test

Status: accepted
Date: 2026-11-20

## Context

An implementation records the owner, the procedure and the last/next test date.
A test is evidence that someone checked the control. An attestation is different:
the owner states that the implementation is still true as described.

Both matter. A test can pass while the owner knows the description is stale; an
owner can attest while no independent test has run. Conflating them hides one of
the two.

## Decision

1. **An attestation is a dated request and response.** The request records who
   asked, when, the due date and what should be confirmed. The response records
   the owner, the time, the statement and whether it was attested or declined.
2. **The owner or an admin responds.** Another editor cannot answer on the
   owner's behalf. The owner is `implementation_owner_id`, not a role.
3. **One pending request per implementation.** A second request returns the
   existing pending record instead of creating a duplicate.
4. **The statement is kept as given.** A later change is a new request. Do not
   rewrite an old statement to make the history look better.
5. **Expiry is explicit.** A pending request past its due date becomes expired.
   Expiry does not invent a response and does not change the implementation.
6. **Attestation is not a test.** It does not update `last_tested_at` or
   `next_test_at`, and it does not prove effectiveness. It is a statement by the
   accountable person.
7. **No campaign engine in this slice.** Requests are per implementation. There
   is no batch launch, sampling, reminder or escalation. A future campaign can
   group these records without changing the statement model.
8. **Attestation is not a policy acknowledgement.** A policy acknowledgement is
   tied to a policy revision; a control attestation is tied to an implementation
   and its owner.

## Consequences

- The programme can show that the owner confirmed the control, not only that a
  test once passed.
- A declined attestation is a visible signal; the owner should raise a finding or
  update the implementation rather than leave it declined.
- The queue is only useful if owners see it. The dashboard badge and "My
  attestations" page are the prompt; there is no email.
- Expiry produces a clean audit trail but no answer. An expired request is not a
  confirmation.
- Per-implementation requests avoid a campaign model, but a large programme will
  want batching later. The record shape supports that.

## Alternatives considered

- **Use the audit item as the attestation.** Rejected: an audit item is a test
  record, not the owner's statement, and it usually belongs to a different person.
- **Use policy acknowledgement.** Rejected: a policy is not a control
  implementation, and the revision semantics are different.
- **Auto-attest when the next test date is future.** Rejected: a date is not a
  statement by the owner.
- **Campaign with sampling in the first slice.** Rejected: more schema and UI for
  little value until the basic request/response loop is used.
- **Allow any editor to attest.** Rejected: accountability belongs to the owner;
  an admin can act as a fallback.
