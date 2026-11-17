# 0008 — Evidence ledger and migration change control

Status: accepted
Date: 2026-11-17

## Context

The platform holds evidence that may be used to defend an audit decision. Two
weaknesses made that evidence easy to undermine:

1. Evidence files could be deleted or repointed at a different file with no
   record of who did it or why. A database backup might recover the row, but not
   the fact that the change happened.
2. Schema changes were only protected by review. A migration could be edited
   after it was applied, and nothing compared the database history with the
   committed files.

The requirement is verifiable history: once evidence is submitted in a production
instance it must remain referenced, and every change must be explainable and
detectable. This is an evidence-integrity control, not a document-management
feature.

## Decision

### Evidence chain of custody

- Every file-bearing evidence model uses `HasEvidenceLedger`: audit and data
  request attachments, survey attachments, vendor documents, and policy
  documents.
- The SHA-256 of the file is recorded on submission.
- Each evidence record has an append-only ledger (`evidence_ledger_entries`) with
  a per-subject hash chain: sequence, event, actor, reason, file hash, previous
  hash, and entry hash.
- `evidence_states` records the current state (`active`, `superseded`,
  `withdrawn`) without changing the evidence tables.
- Withdrawal requires a reason and creates a `withdrawn` entry. It does not
  delete the file or the record.
- Replacing evidence is `superseded` + a new submission, linked from the old
  state.
- SQLite and MySQL/MariaDB triggers block UPDATE and DELETE on ledger entries.
  The model also refuses both.
- Production mode is a one-way setting (`instance.mode=production`). Promotion
  backfills existing evidence first. In production, evidence records cannot be
  deleted or repointed, including soft deletes.
- `opengrc:evidence:verify` recomputes file hashes and every chain entry, checks
  withdrawal reasons and orphaned entries, and exits non-zero on failure. The
  doctor reports ledger health.

### Migration change control

- `database/migration-manifest.json` records the SHA-256 of every migration.
- `opengrc:migrations:manifest` regenerates it; it is committed with the
  migration that changed the schema.
- `opengrc:migrations:verify` checks files against the manifest, compares the
  checksum recorded when each migration was applied, and reports unknown or
  pending migrations. CI runs it.
- `migrations.checksum` and `migrations.applied_at` are filled after a migration
  run, so the database carries the version that was actually applied.

This keeps schema changes easy: add a migration, run one command, commit both.
The control is the checksum, not a heavier process.

## Why not Alembic as the authoritative migrator

Alembic is Python/SQLAlchemy's migration tool. The application is Laravel/PHP.
Making Alembic authoritative would mean rewriting 110 migrations, the installer,
seeders, the schema snapshot, and the test database bootstrap, and running a
Python runtime beside every deployment. It would not add tamper evidence by
itself: an Alembic version table is as editable as Laravel's `migrations` table.

The requirement was verifiable history, not a specific tool. Checksums, an
append-only ledger, database triggers, and a verifier meet that requirement while
keeping the migration workflow that Eloquent, tests, and the installer depend on.

If the organisation later standardises on a Python data platform, Alembic can own
a separate integration schema. That is a different ADR.

## Consequences

- Uploads pay one SHA-256 pass (files are capped at 20 MB by upload rules).
- Verification is proportional to the evidence store; run it in CI and on a
  schedule, not on every page view.
- Promotion to production is irreversible without direct database intervention,
  which is deliberate.
- Existing evidence is backfilled with a `backfilled` marker and the uploader as
  actor where known.
- Operators must set `APP_VERSION` so ledger entries identify the release.
