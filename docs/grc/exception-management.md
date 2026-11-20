# Exception management

An exception is a formal, time-bound decision to accept a deviation from a
requirement. It is not a note that says "we know". It has a named requirement, a
reason, a risk assessment, compensating controls, a second person and an end date.

Policy exceptions are the first implemented scope. Control, vendor, asset and risk
exceptions are later, separate records with their own subject. Risk acceptance is
not a policy exception; it is a treatment decision on a risk.

See ADR 0024 for the decision and its alternatives.

## Lifecycle

| State | Meaning | Who acts |
|---|---|---|
| Pending | Requested, not yet decided | Requester, approver |
| Approved | Accepted for a defined period | Approver |
| Denied | Refused; the requirement still applies | Approver |
| Expired | The approved period ended | Scheduled command |
| Revoked | Withdrawn before expiry | Approver or programme lead |

Only **Approved** is an active waiver, and only while the effective date has
passed and the expiry has not. Everything else is a gap, not a pass.

## Required fields

| Field | Why it exists |
|---|---|
| Policy | The requirement being waived |
| Name | A short, searchable identifier |
| Description | What the deviation is |
| Business justification | Why the deviation is necessary |
| Risk assessment | What exposure the deviation creates |
| Compensating controls | What reduces that exposure now |
| Requester | Who asked |
| Approver | Who accepted; must be a different person |
| Requested / effective / expiration dates | When the decision starts and ends |

An approval without a risk assessment or compensating controls is a note, not a
control. An approval without an expiry is an open-ended waiver; treat it as a
programme decision that needs an explicit record.

## Approval rules

- The approver must differ from the requester.
- Approval requires an expiration date. A far-future date is still a review date.
- The record is soft-deleted only for a correction; revoke or expire instead of
  deleting.
- Re-approval is a new decision with a new justification and a new date. Never
  roll a date forward silently.
- If the requirement is a control, link the exception to the control or risk so
  the assurance view shows the gap.

## The register

**Entities → Exceptions** is the cross-policy register. It shows status, policy,
expiry and compensating controls for every exception, including soft-deleted
records via the filter. It is read-only: request and edit an exception on the
policy that owns the requirement.

Filters include status, policy, expiring in 30 days and expired. The list is the
review queue for an approver or auditor.

## Expiry enforcement

```bash
php artisan opengrc:exceptions:expire --dry-run
php artisan opengrc:exceptions:expire
```

The command marks approved exceptions past their expiration date as Expired. It
does not renew or extend anything. The scheduler runs it daily at 00:10.

If the scheduler stops, an expired exception can still read as Approved. That is
an operational gap: check the scheduler in `opengrc:doctor` or your monitoring,
not only the register.

## Review cadence

| Frequency | Activity |
|---|---|
| Weekly | Review pending requests; approve, deny or ask for missing evidence |
| Monthly | Review exceptions expiring in 30 days; confirm the compensating controls still operate |
| Quarterly | Confirm every active exception is still necessary; revoke what is not |
| Annually | Re-baseline the exception policy and approval thresholds |

## Evidence and audit

The exception record is evidence that a deviation was governed. It supports the
policy and control it waives, and it is readable by an auditor without opening
every policy.

A complete package for an exception includes the request, the risk assessment,
the compensating controls, the approval, the expiry, and the evidence that the
compensating controls operated. A register with no evidence behind it proves only
that someone typed a justification.

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| Permanent exceptions | Nobody owns the end date | Require an expiry; re-approve on a schedule |
| Self-approval | Approval is a formality | Enforce a different approver |
| "Compensating controls: monitoring" | No specific control or evidence | Name the control and link its evidence |
| Exceptions hidden in email | No register | Record every exception; link it to the policy |
| Expired exceptions still approved | No scheduled enforcement | Run the expiry command and monitor it |
| Exception used instead of a risk acceptance | The decision is in the wrong record | Create a risk acceptance with an owner and expiry |

## Current limits

- Policy exceptions are implemented. Control, vendor, asset and application
  exceptions are not yet first-class records.
- There are no reminder notifications before expiry; the register and the
  scheduled command are the current controls.
- There is no multi-step approval chain; the approver is a single, named person.
- The register does not attach evidence files; link the exception to an audit
  item or documentation until an exception evidence attachment exists.
