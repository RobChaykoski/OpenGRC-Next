# Audit ledger

The activity log records what happened. The audit ledger is the evidence that the
record was not edited afterwards. ADR 0056 is the decision; this page is what it
proves and how to check it.

## What it is

Every time an activity row is created, the application appends a row to
`audit_ledger_entries`:

| Field | Meaning |
|---|---|
| `activity_id` | The activity it mirrors (plain column, no foreign key) |
| `event`, `log_name` | What happened and in which channel |
| `subject_type`, `subject_id` | The record that changed |
| `actor_type`, `actor_id` | Who changed it |
| `description` | The activity description, verbatim |
| `properties_sha256` | A digest of the activity attribute changes and properties, not the values |
| `hash_version` | Which digest algorithm the entry was written with |
| `occurred_at` | When the activity was recorded |
| `previous_hash`, `entry_hash` | The hash chain |

The row is append-only: the model refuses update and delete, and the database has
`BEFORE UPDATE` and `BEFORE DELETE` triggers on SQLite and MySQL/MariaDB.

## What it proves

- The sequence of recorded events, in order, without gaps that the chain can hide.
- That no ledger entry was edited, reordered or removed: the next entry's
  `previous_hash` and the entry's own hash stop matching at the break.
- That the activity row still matches what was mirrored: verification re-hashes
  the live `description`, the tracked attribute changes and the properties and
  compares them.

Entry hashes are versioned. Version 1 hashed `properties` only; version 2 hashes
`attribute_changes` and `properties`. Old entries verify with the algorithm they
were written with, so the chain is not rewritten when the digest improves.

## What it does not prove

- **Intent or truth.** It proves the record, not that the record was accurate.
- **Coverage of unlogged writes.** A model without `LogsActivity`, or a raw SQL
  update, produces no activity and therefore no ledger entry. The fix is to log
  that path, not to add a second mechanism.
- **Immutability against the database superuser.** A trigger can be dropped. The
  chain then shows the gap at verification; it does not prevent it.
- **Legal retention.** The ledger is never pruned by the application. Whether it
  may be kept, and for how long, is a records decision.

## Verify

```bash
php artisan opengrc:audit:verify
```

- **Chain intact** → exit 0, with the entry count and how many mirrored activity
  rows have been pruned.
- **Chain broken** → exit 1, naming the first entry that does not match.
- **Activity altered** → exit 1, with the count of rows whose description or
  properties no longer match the ledger.

`php artisan opengrc:doctor` runs the same check: "Audit ledger" passes on an
intact chain and fails on a broken or altered one.

The scheduled path is `php artisan opengrc:integrity:verify`. It runs the audit and
evidence checks, persists one row per chain in `integrity_checks`, and exits
non-zero on a break. The dashboard shows the newest result as **Record integrity**;
a run older than `integrity.stale_after_hours` is surfaced as attention, so a
stopped scheduler does not look like a healthy chain. Run history is pruned after
`integrity.retention_days`; the ledger itself is untouched.

## Pruning

`php artisan activitylog:clean` prunes old activity rows on the schedule. The
ledger rows remain, `activity_id` still points at the removed detail, and
verification reports the count as pruned rather than as a failure. If an
investigation needs the detail, it must exist somewhere else by then; the ledger
keeps the event, the actor and the timestamp.

## Coverage

Everything that uses `LogsActivity` is covered. When you add a model that carries
security or compliance meaning, add the trait and choose its logged fields. Do
not log secrets, tokens, request bodies or personal data beyond what the audit
question needs: the ledger hashes the changes, but the activity row still stores
them.

The governance registers that did not log changes now do, with an explicit field
set and a test that locks the list: access grants and reviews, approval requests
and steps, assets, assistant proposals, control attestations, crosswalks and
validation rules, DLP policies, findings and suppressions, evidence sources and
streams, exercises, findings, policies, acknowledgements and exceptions,
protected resources, remediation tasks and vendor subcontractors.

The field sets deliberately exclude free text and credentials: a suppression's
justification, an attestation statement, an approval comment, an exception's
rationale and an evidence source's configuration are not logged. A test proves
the exclusion for the suppression and the source.

## Limits

- There is no ledger viewer. The dashboard shows the newest verification status;
  the detail stays in `opengrc:audit:verify` and the doctor.
- Database triggers are installed on SQLite and MySQL/MariaDB. On PostgreSQL the
  append-only guard is the model layer only; verification still detects an edit,
  but a direct database writer is not blocked. This is a known gap.
- The chain depends on serialised writers; concurrent inserts can interleave
  hashes if two processes race. The database serialises the writes in practice,
  and verification reports an ordering break rather than hiding it.
- The ledger is a mirror of activity, not an event-sourced system. Rebuilding
  state from it is not supported.
