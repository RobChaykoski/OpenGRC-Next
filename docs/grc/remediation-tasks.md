# Remediation tasks

A finding says what is wrong. A remediation task says who is doing the work, by
when, and whether it is blocked. It is the bridge between a gap and a closed gap.

See ADR 0044. This is a work record, not a project plan.

## What it is

A task with:

- title and description;
- a link to at least one finding, risk or control;
- an owner;
- a due date;
- status: open, in progress, blocked, done or cancelled;
- notes;
- a completion time when marked done.

## Creating a task

Open **Entities → Remediation tasks**, or create one from the task register. Link
the finding, risk or control it belongs to. At least one link is required.

One finding can have several tasks. The same task can link to a finding and the
risk it contributes to.

## Status and completion

| Status | Meaning |
|---|---|
| Open | Not started |
| In progress | Someone is working on it |
| Blocked | Waiting on a decision, access or another team |
| Done | The work is complete |
| Cancelled | The work is no longer needed |

Marking a task done records the completion time. Reopening it clears the time.

Only open, in progress and blocked tasks can be overdue.

## What it does not do

- It does not resolve the finding or close the risk.
- It does not verify that the fix works.
- It does not create child tasks, milestones or dependencies.
- It does not sync with Jira, GitHub or a ticket system.
- It does not notify the owner.
- It does not replace the finding's own owner and due date.

## Reviewing remediation

1. Open the task register and filter to open or overdue.
2. Check the owner and date.
3. If blocked, record what it is waiting on in the notes.
4. When the work is complete, mark it done.
5. Verify the outcome in the finding, audit item or control test.
6. Resolve the finding or update the risk only after verification.

## Evidence

For an audit, keep:

- the task and its linked finding or risk;
- the owner and due date;
- the completion time and notes;
- the verification in the finding or audit record;
- the external ticket id in the notes if one exists.

A completed task is not proof of a fix. It is proof that the work was recorded and
closed; the verification is separate.

## Limits

- No dependencies or scheduling.
- No milestones or child tasks.
- No ticket sync or notification.
- No automatic finding resolution.
- No time tracking or estimates.
- A task must link to a finding, risk or control; it is not a general to-do list.
