# 0055 — The assistant proposes changes; a human applies a typed diff

- Status: accepted
- Date: 2026-11-21

## Context

The assistant is a fixed, read-only question catalogue (ADR 0046). It answers
bounded questions and labels its narrative as interpretation. The obvious next
request is "let it fix the thing it found" — update an implementation, attach
evidence, open a remediation task.

An agent with write tools inside a GRC system can approve its own rationale,
attach the wrong evidence to a control, and leave a record that looks like human
work. The failure is not a bad sentence; it is a false attestation.

## Decision (proposed)

1. **The model never writes.** It emits a *proposal*: one or more operations from
   a fixed, typed catalogue, the target records, the before/after values, the
   facts it used and a short rationale. It cannot emit SQL, a model method name or
   free-form code.
2. **The operation catalogue is small and allow-listed.** Examples: set an
   implementation's status, attach an existing evidence record to an
   implementation, create a remediation task with an owner and date, add a note to
   a finding. Denied by construction: roles and permissions, MFA, access rules and
   policies, access decisions, evidence deletion or mutation, risk acceptance,
   policy acknowledgement, anything append-only.
3. **A human approves through the existing approval chain.** The proposal becomes
   an `ApprovalRequest` (ADR 0043) linked to an `AssistantProposal`. Approval is
   the usual separation of duties; the proposer is a run, not a user.
4. **Application is deterministic and server-side.** An approved proposal is
   applied by a service that validates the target, the current values (optimistic
   check against the diff's `before`), the actor's permission at apply time, and
   the operation schema. If anything moved since the proposal, it fails closed and
   asks for a new proposal.
5. **Rollback is a compensating action.** Each applied operation stores its
   inverse. Rollback is a new record with its own approver and reason; nothing is
   deleted, and an append-only record cannot be rolled back at all.
6. **The evidence trail is complete.** Proposal, prompt facts, approval,
   application, and rollback are recorded with the run id, the model and the
   policy version. The prompt and response follow the redaction rules in ADR 0046.
7. **Execution is rate-limited and bounded.** A proposal carries at most a small
   number of operations; the whole proposal can be rejected with one click; a
   proposal that fails validation twice is dropped, not retried in a loop.

## Implementation

`AssistantProposal` stores the normalised operations, the before/after values,
the state and the human decisions. `AssistantOperations` is the catalogue and the
security boundary; it resolves the target model, captures `before`, validates
`after`, refuses a moved record, and computes the inverse.

`AssistantProposalService` enforces the lifecycle and requires an editor or
administrator for approve, reject, apply and rollback. `apply` runs in a
transaction; a failure marks the proposal `failed` with the reason and leaves the
records untouched. The queue is **Admin → System → Assistant proposals**.

The assistant's question catalogue does **not** call the service yet. Wiring a
producer is a reviewed use case with its own question, facts and tests; the
catalogue is what makes that safe to add.

Addendum (2026-11-22): the first producer is wired. The overdue-tests answer now
offers an explicit **Propose next test dates** action, which creates one bounded
proposal for up to five implementations. The human approval and the separate apply
are unchanged; see `docs/grc/ai-assistant.md`.

## Consequences
- The assistant can reduce toil without becoming an approver.
- "The AI changed it" is never an explanation an auditor has to accept: a named
  human approved a specific diff, and the diff is in the record.
- The catalogue is the security boundary. Adding an operation is a reviewed change
  with a permission test and an inverse, not a prompt edit.
- Rollback of an append-only record is impossible by design; the proposal engine
  must refuse those operations before a human sees them.
- A compromised model can still waste a reviewer's time with plausible proposals.
  The defence is the typed catalogue and the before/after check, not trust.

## Alternatives considered

- **Free-form tool calls with a confirmation dialog.** Rejected: confirmation of
  an opaque action is not review; the reviewer cannot see what will change.
- **Let the assistant write directly into drafts that humans publish.** Rejected:
  drafts still land in the same tables and are counted by reports.
- **A separate "AI sandbox" database.** Rejected: the value is the real record, and
  a copy drifts.
- **No writes, ever.** Still the safest position and the current one; this ADR is
  proposed because the approval object and typed diff make a controlled version
  possible.

## References

- ADR 0043 — approval requests are an ordered chain
- ADR 0046 — the assistant is a fixed read-only catalogue
- ADR 0019 — append-only forensic and evidence handling
- `docs/grc/ai-assistant.md` — current behaviour and limits
