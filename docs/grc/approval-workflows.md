# Multi-step approval requests

Some decisions need more than one approver. An approval request records an ordered
chain: who must decide, in what order, what they said and when.

See ADR 0043. This is a chain, not a per-field workflow engine.

## What it is for

- a policy exception that needs security and then the data owner;
- a vendor onboarding that needs procurement and then security;
- a change that needs the control owner and then the accountable executive;
- a crosswalk or other decision where a single signature is too weak.

It is not a task manager, a ticketing system or a replacement for the audit trail.

## Creating a request

1. Open **Entities → Approval requests**.
2. Enter a specific title and a description of the decision.
3. Set a decision due date if the programme has one.
4. Save.
5. On the request page, add the approval steps in order. Each step names one
   approver.

Steps can be added only before the first decision. The same person cannot hold
two steps in one chain.

## Deciding

The current approver sees the request in **My approvals** and in the dashboard
**Approvals** tile.

- **Approve** records the decision, the person and an optional comment.
- **Reject** requires a reason. The request is rejected and every later pending
  step is marked skipped.

An admin can act as a fallback approver. The person who actually decided is
recorded, so the fallback is visible.

A later step cannot be approved before the earlier step is decided.

## Status

| Status | Meaning |
|---|---|
| Pending | The chain is open; one step is current |
| Approved | Every step was approved |
| Rejected | A step was rejected; later steps were skipped |
| Cancelled | The requester or an admin stopped the request |

Cancelling skips the pending steps and completes the request.

## Reading the register

The table shows the request, status, current approver, approved-step count, due
date and requester. Overdue pending requests are highlighted and filterable.

The request page shows the chain: sequence, approver, status, who decided, when
and the comment.

## Relationship to the single-signature Approval record

OpenGRC also has a single `Approval` record used by checklist sign-off. That is a
snapshot signature on an existing record. A multi-step request is a separate
register because the lifecycle, the steps and the queue are different.

## Limits

- No per-field conditional routing.
- No delegation or reassignment.
- No reminders, email or chat notification.
- No attachment on a step; use the description and the linked record.
- No automatic link to a policy, risk or vendor record yet; put the reference in
  the title or description.
- A stalled request has no escalation; the queue and due date are the prompt.
