# Risk acceptance

Accepting a risk is a decision to live with a residual exposure. It is not the
same as a mitigation, which is an action that changes the exposure. An acceptance
needs a named person, a date, a rationale and an end date.

See ADR 0031 for the boundary.

## When to accept

Accept when treatment would cost more than the exposure, when the risk is
inherent to the activity, or when a temporary exception is the only practical
option. Do not accept:

- a Very High risk without a dated treatment plan or an explicit programme
  decision;
- a risk whose controls are unknown or untested;
- a risk with no owner;
- an open-ended exposure because nobody had time to decide.

## Fields

| Field | Why it exists |
|---|---|
| Acceptance status | None, pending, accepted, expired or revoked |
| Accepted by | The person accountable for the decision |
| Accepted on | When the decision was made |
| Acceptance expires | The date the acceptance must be reviewed |
| Acceptance rationale | Why the residual exposure is acceptable |

The risk's own status (Not Assessed, In Progress, Assessed, Closed) is separate.
An accepted risk can still be open for treatment.

## Status and expiry

| Status | Meaning |
|---|---|
| None | No acceptance decision |
| Pending | A decision is being sought |
| Accepted | A named person accepted the residual risk |
| Expired | The acceptance date passed |
| Revoked | The acceptance was withdrawn |

```bash
php artisan opengrc:risks:acceptance --dry-run
php artisan opengrc:risks:acceptance
```

The command marks accepted risks past their expiry as Expired and reports those
expiring in the next 30 days. The scheduler runs it daily at 00:30.

## Review cadence

| Frequency | Activity |
|---|---|
| Monthly | Review acceptances expiring in 30 days |
| Quarterly | Confirm the rationale still holds and the owner is current |
| On change | Re-accept after a scope, control or exposure change |
| Annually | Re-baseline acceptance thresholds and authorities |

## Acceptance vs mitigation

| Record | Answers |
|---|---|
| Mitigation | What did we do about the risk? |
| Acceptance | Who decided to live with what remains, and until when? |

A risk can have both. A mitigation record with the strategy "Accept" and no
acceptance record is incomplete.

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| Acceptances with no end date | No expiry required | Require an expiry for Accepted |
| Acceptor is always the owner | No second pair of eyes | Apply the programme's independence rule |
| Rationale is "accepted by management" | No decision content | Record the actual reason and conditions |
| Expired acceptances still cited | No enforcement | Run the daily command; review the list |
| Acceptance used instead of treatment | The cheap option won | Require a treatment decision for High and Very High |
| Risk deleted after closure | Evidence destroyed | Keep the record; use status and closure notes |

## Evidence and audit

For each accepted risk, an auditor should find:

- the risk record and its residual score;
- the named acceptor and acceptance date;
- the rationale and any conditions;
- the expiry and the review history;
- the controls or compensating measures that make the residual acceptable;
- the next decision or closure.

A risk acceptance is not a control test. It is evidence that the organisation
made a documented decision about the exposure.

## Limits

- The register does not calculate appetite thresholds; a programme defines them.
- The acceptor is a user reference, not a signature or an external approver.
- The daily command changes the status, not the risk treatment.
- There is no multi-step approval chain; the acceptance is a single named
  decision.
- A risk can be accepted without a linked mitigation; the rationale should explain
  why that is appropriate.
