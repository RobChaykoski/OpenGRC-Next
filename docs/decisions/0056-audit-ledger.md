# 0056 — Audit events are mirrored into an append-only ledger

- Status: accepted
- Date: 2026-11-22

## Context

The platform roadmap records the gap plainly: audit events live in Spatie's
`activity_log`, which is an ordinary table. Anyone with database access, or an
application bug, can edit or delete the record of who changed what. An audit trail
that can be edited is a log, not evidence.

The tempting fixes are both wrong. Replacing the activity log with an event store
is a rewrite of every write path. Writing a second manual audit call next to every
`LogsActivity` model duplicates the coverage problem: the one path somebody
forgets is the one that matters.

## Decision

Keep the activity log as the operational detail and mirror every activity event
into an append-only, hash-chained ledger.

1. **One observer, full existing coverage.** The ledger appends when an
   `Activity` row is created. Every model already using `LogsActivity` is covered
   without touching its write path.
2. **The ledger stores the event, not the payload.** `audit_ledger_entries` holds
   the event, log name, subject, actor, description, the time, and a SHA-256 of
   the activity properties. It deliberately does not copy the properties: the
   activity log is the detail, and duplicating it would double the personal data
   in the system.
3. **The chain is the evidence.** Each entry hashes the previous entry's hash and
   its own fields. Editing, reordering or removing an entry breaks the chain at a
   known id.
4. **Append-only at two layers.** The model refuses update and delete, and the
   database has UPDATE and DELETE triggers (SQLite and MySQL/MariaDB, the
   supported targets), the same pattern as the evidence ledger (ADR 0008).
5. **Verification re-hashes the live rows.** `php artisan opengrc:audit:verify`
   recomputes the chain and re-hashes each mirrored activity row. A changed
   description or property set is reported as altered; a pruned activity row is
   reported as pruned, because `activitylog:clean` is expected to remove old
   detail while the ledger entry remains. `opengrc:doctor` fails on a broken chain
   or an altered row.
6. **`activity_id` is a plain column, not a foreign key.** Pruning activity must
   not be blocked by the ledger, and the ledger must not be changed by the prune.
   The ledger keeps the id as a reference; the relation simply returns null after
   pruning.
7. **Fail closed.** If the ledger append fails, the activity write fails with it.
   A write that cannot be recorded is not silently accepted as unaudited.

## Consequences

- Configuration changes, role changes and record edits that already log activity
  now have a tamper-evident trail, without a second write path per model.
- A write path that bypasses activity logging is invisible to the ledger. The fix
  is to add `LogsActivity` to that model, not to add a parallel audit call.
- The ledger duplicates the description and the timestamps, not the property
  values. An investigator still needs the activity row for detail; the ledger
  proves that the row existed and has not changed.
- The chain detects alteration and ordering, not intent, and depends on
  serialised writers, exactly like the access decision chain (ADR 0049).
- The ledger is never pruned. Activity detail may be pruned; the ledger entry and
  its verification state remain, with the activity link reported as pruned.
- No screen for the ledger is added. `opengrc:audit:verify` and the doctor are the
  interface; a read-only viewer would be a report, not a control.

## Alternatives considered

- **Replace the activity log with an event-sourced ledger.** Rejected: it changes
  every write path and every consumer of the activity log for a property the
  mirror already provides.
- **Store the full properties in the ledger.** Rejected: a second copy of the
  same personal data, with a longer retention and a wider audience.
- **Manual audit calls per service.** Rejected: coverage by discipline fails
  exactly where it matters, in the least-used path.
- **Application-only append-only guard.** Rejected: the threat includes database
  access; the trigger is the part that survives an application bug.
- **Block pruning of activity.** Rejected: operational detail has a retention
  period; the ledger is what survives it.

## References

- ADR 0008 — evidence ledger, append-only records and migration integrity
- `docs/security/audit-ledger.md` — what it proves, coverage and verification
- `docs/FUTURE-ROADMAP.md` — the immutable audit-log pillar

Addendum (2026-11-22): activity changes are stored in `attribute_changes`, not
`properties`, so version 1 hashed an empty payload. The digest is versioned
(`hash_version`): old entries verify with the old payload, new entries hash the
attribute changes as well. The governance registers listed in the operating doc
now log an explicit field set.
