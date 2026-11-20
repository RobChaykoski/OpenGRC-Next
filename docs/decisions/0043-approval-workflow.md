# 0043 — Approval requests are an ordered chain, not a workflow engine

Status: accepted
Date: 2026-11-20

## Context

Some decisions need more than one person: a policy exception, a vendor, a change,
a crosswalk. The existing `Approval` record captures a single signature on an
approvable record. It does not model "first security, then the data owner, then
the accountable executive".

A general workflow engine with per-field routing, delegation, reminders and
conditional branches is a product in itself. OpenGRC does not need that to make a
multi-step decision auditable.

## Decision

1. **A request is a register record.** It has a title, description, requester, due
   date, status and an ordered set of steps.
2. **The chain is ordered.** Only the first pending step is actionable. A later
   step cannot be approved early.
3. **Each step names one approver.** The named approver or an admin can decide it.
   The person who actually decided is recorded, so an admin fallback is visible.
4. **A rejection ends the chain.** Later pending steps are marked skipped; they do
   not stay open. The request is rejected and complete.
5. **All steps approved means approved.** The request completes when no pending
   step remains.
6. **No duplicate approver.** One person cannot hold two steps in the same chain;
   that would fake separation of duties.
7. **No steps after the chain starts.** Steps can be added only before the first
   decision. A later change is a new request.
8. **No per-field routing, delegation, reminders or notifications.** The register
   and the "My approvals" queue are the workflow. The programme watches the queue.
9. **This is not the existing single-signature `Approval` record.** That record
   remains for checklist sign-off. The new request is for a multi-step decision.

## Consequences

- A programme can show who approved what, in what order, and who rejected it and
  why, without a workflow product.
- The request is standalone. It is not attached to a policy, risk or vendor
  record yet; the title and description say what is being approved.
- A stalled chain is visible as pending with a current approver. There is no
  escalation; the programme must watch it.
- Admin fallback is useful but can weaken separation. The recorded `decided_by`
  makes the fallback visible; review it rather than hiding it.
- No notification means the dashboard tile and "My approvals" page must be part
  of the operating rhythm.

## Alternatives considered

- **Extend the existing `Approval` model with steps.** Rejected: it is a signature
  snapshot used by checklists; mixing a chain into it would complicate both.
- **Build a per-field workflow engine.** Rejected: large scope, hard to explain and
  unnecessary for the decisions in scope.
- **Allow any admin to approve any step without recording the fallback.** Rejected:
  hides who really decided.
- **Leave later steps pending after a rejection.** Rejected: it creates a queue of
  work that can never be completed.
- **Allow the same person on two steps.** Rejected: it defeats the separation the
  chain exists to provide.
