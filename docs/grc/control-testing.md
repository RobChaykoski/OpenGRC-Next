# Control testing cadence

An implementation records how a control is operated, who owns it, when it was
last tested and when the next test is due. The dates are the programme's promise;
nothing in OpenGRC enforces them automatically.

This page describes the report that makes overdue tests visible.

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

## Running it

There is no mail sender and no notification channel. Run the command where the
programme already watches:

- a weekly cron job that writes the table to a shared inbox or a log;
- a monitoring check that uses `--json` and alerts on `overdue > 0`;
- a CI job with `--fail-on-overdue` for a team that wants a failing build.

Do not put a failing cron job in place without an owner for the output. A report
nobody reads is worse than no report.

## After a test

1. Record the result on the implementation or the audit item.
2. Update **last tested** to the date the test was completed.
3. Set **next test** to the next agreed date.
4. If the test found a gap, raise a finding and link it to the implementation.
5. If the result changes the control's effectiveness, record that too.

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

- No reminder email, chat message or ticket creation.
- No automatic next-date calculation from a cadence field.
- No separate test log; the implementation carries the last and next date, and
  audit items carry the detail.
- The report lists implementations, not controls without an implementation.
- A test can be late without being wrong; the report shows the gap, a person
  decides what it means.
