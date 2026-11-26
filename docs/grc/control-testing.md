# Control testing cadence

An implementation records how a control is operated, who owns it, when it was
last tested and when the next test is due. The dates are the programme's promise;
OpenGRC can remind the owner, but it does not test the control or change the
dates for you.

This page describes the report that makes overdue tests visible, and the in-app
reminder that carries the same queue to each owner.

## Fields

| Field | Meaning |
|---|---|
| Implementation owner | The person accountable for the control being operated |
| Test procedure | How the control is tested, not only what it says |
| Last tested | The date the last test was completed |
| Next test | The date the next test is due |

The next test date is set by the programme, not calculated from a fixed interval.
A quarterly control, an annual control and a control tested after every change all
use the same fields with different dates.

## The report

```bash
# Tests due today or already overdue
php artisan opengrc:controls:testing

# Include tests due in the next 30 days
php artisan opengrc:controls:testing --days=30

# One owner's queue
php artisan opengrc:controls:testing --owner=12 --days=14

# Machine-readable output for a monitoring check
php artisan opengrc:controls:testing --json

# Exit non-zero when any listed test is overdue (for cron or CI)
php artisan opengrc:controls:testing --fail-on-overdue
```

The table shows the implementation code and title, owner, next test date, days
overdue and the linked control identifiers. An implementation with no next test
date is not listed; it is unscheduled, not overdue.

The dashboard's **Control tests overdue** tile uses the same overdue rule, so the
queue is visible without opening the command output.

## Reminders

The same queue is delivered to each implementation owner's notification bell
(the database notification the admin and app panels already poll):

```bash
# Send due and overdue reminders now
php artisan opengrc:controls:testing:notify

# Include tests due in the next 30 days
php artisan opengrc:controls:testing:notify --days=30

# Show what would be sent, without writing
php artisan opengrc:controls:testing:notify --dry-run
```

- A test inside the window is a **Control test due** warning; a past-due test is
  a **Control test overdue** danger. Today counts as due, not overdue.
- Each implementation is reminded once per stage and next-test date. The daily
  scheduled run therefore stays quiet instead of repeating itself, and a retest
  with a new date can notify again. The dedupe marker is in the cache, so a cache
  flush can repeat a reminder but never drops one.
- Only the implementation owner is routed the due and overdue reminders. An
  implementation with no owner is counted as **not routed**; assigning an owner
  is the fix.
- **Escalation is opt-in.** Set `CONTROL_TEST_ESCALATION_AFTER_DAYS` (default
  `0`, off). When enabled, an assigned test that stays overdue past that many days
  is raised to every administrator once per test date, and unowned tests past the
  threshold become one weekly digest per administrator. The escalation names the
  owner and the days overdue; it does not close anything or open a finding.
  Without an administrator account, nothing is escalated and the command says so.
- The scheduler runs the command daily at 07:00 (`app/Console/Kernel.php`).
- Reminders are in-app only. There is no email, chat message or ticket; the
  message carries the code, title, date and owner, never the notes or test
  procedure.

## Running the report

The report remains the right input for an external check, for example:

- a weekly cron job that writes the table to a shared inbox or a log;
- a monitoring check that uses `--json` and alerts on `overdue > 0`;
- a CI job with `--fail-on-overdue` for a team that wants a failing build.

Do not put a failing cron job in place without an owner for the output. A report
nobody reads is worse than no report; the in-app reminder is for the owner who is
already in the product.

## After a test

1. Record the result on the implementation or the audit item.
2. Update **last tested** to the date the test was completed.
3. Set **next test** to the next agreed date.
4. If the test found a gap, raise a finding and link it to the implementation.
5. If the result changes the control's effectiveness, record that too.
6. Attach the screenshot, export or report that shows the control operating to the
   implementation's **Evidence** tab; put the test record on the audit item.

Completing an audit item does not stamp the implementation dates. Someone must
update them; that is deliberate, because the audit item and the control test are
not always the same event.

## Evidence

The report is a queue, not evidence that a test happened. For an audit, keep:

- the implementation record with its procedure and dates;
- the audit item or test record for each completed test;
- the finding raised from a failed test;
- the date the next test is due, so the cadence is visible.

## Limits

- Reminders and escalation are in-app only: no email, chat message or ticket
  creation.
- Reminder and escalation dedupe is a cache marker, not a stored record; a cache
  flush can repeat a notification, and a notification is not evidence that anyone
  read it.
- An implementation with no owner is counted and skipped for reminders; it reaches
  administrators only through the weekly escalation digest once that is enabled.
- Escalation does not open a finding, change the dates or reassign the control.
- No automatic next-date calculation from a cadence field.
- No separate test log; the implementation carries the last and next date, and
  audit items carry the detail.
- The report lists implementations, not controls without an implementation.
- A test can be late without being wrong; the report shows the gap, a person
  decides what it means.
