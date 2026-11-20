# 0044 — Remediation tasks are work records, not a project plan

Status: accepted
Date: 2026-11-20

## Context

A finding says what is wrong. A risk says what could happen. Neither says who is
doing the work, by when, or whether it is blocked. The finding had an owner and a
due date, but a finding can need several pieces of work, and the same work can
close more than one finding.

A full project plan with dependencies, milestones, estimates and a ticket sync is
a different product. The GRC record needs the accountable work item and its state.

## Decision

1. **A task is a work record.** It has a title, description, owner, due date,
   status, notes and a completion time.
2. **A task links to at least one subject.** A finding, a risk or a control. A
   task with no subject is not a remediation task; it is a note.
3. **Status is explicit.** Open, in progress, blocked, done, cancelled. Only open,
   in progress and blocked count as overdue.
4. **Done stamps the completion time.** Reopening clears it. The timestamp is
   derived from the status, not typed by hand.
5. **No dependencies or milestones.** A large remediation belongs in an external
   tracker; put the ticket id in the notes. The GRC record keeps the accountable
   task, not the plan.
6. **No automatic closure.** Completing a task does not resolve the finding or
   close the risk. A person verifies the outcome and records that decision.
7. **No child tasks or templates.** Split the work into separate tasks if needed;
   do not build a hierarchy in this slice.
8. **No ticket sync or notification.** The due date and the register are the
   prompt.

## Consequences

- A finding or risk can show the work that is meant to close it, with an owner and
  a date.
- Tasks can be filtered by finding, risk, owner, status and overdue state.
- The register can grow quickly. A task per finding is a useful default; a task
  per scanner result is noise.
- Reopening a done task clears the completion time, which keeps the status and the
  timestamp consistent.
- No dependency graph means a blocked task is a label, not a scheduling engine.

## Alternatives considered

- **Use the finding's owner and due date only.** Rejected: one finding can need
  several work items, and the finding due date is not the same as each task.
- **Add a full project plan.** Rejected: scope, permissions and a second tracker;
  external tools already do this.
- **Auto-resolve the finding when all tasks are done.** Rejected: completing work
  is not verifying the fix.
- **Allow tasks with no linked record.** Rejected: it turns the register into a
  general to-do list and loses the GRC relationship.
- **Model milestones in the first slice.** Rejected: a hierarchy adds UI and
  reporting complexity before the basic task is used.
