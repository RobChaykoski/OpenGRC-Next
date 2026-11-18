# 0012 — Canonical control catalogue is configuration, synced into the data graph

Status: accepted
Date: 2026-11-18

## Context

The unified control catalogue in `config/framework_scoping.php` is what lets the
scoping engine de-duplicate overlapping framework requirements. It was
configuration only: nothing joined a canonical outcome to an internal control, an
evidence record, or the matrix page. Every capability that follows (confidence,
assurance score, auditor export) reads from that join, so the catalogue has to
become data without becoming a second, divergent source of truth.

Two sources of truth would drift. Letting users edit canonical controls in the UI
would also make framework coverage non-reproducible: two installs of the same
version would disagree about what a framework requires.

## Decision

1. `config/framework_scoping.php` remains the authored source for canonical
   controls and for which frameworks require them.
2. `canonical_controls`, `canonical_frameworks`, and `canonical_control_framework`
   are derived tables, seeded by `opengrc:catalog:sync`.
3. Sync is idempotent and transactional:
   - controls and frameworks are upserted by `code`;
   - each framework's control set is replaced with the configured set, so a
     removed mapping disappears on the next run;
   - a framework referencing an unknown control fails the whole sync before any
     write, because a silently skipped mapping is a coverage hole.
4. Sync never deletes a canonical control that config no longer declares. It
   reports it instead. Upstream links may already point at the row, and an
   orphaned evidence link is worse than a stale row an operator can retire
   deliberately.
5. Records link to canonical controls by foreign key. The linking step for
   existing internal controls is curated, not fuzzy-matched: unlinked is an
   honest state, a guessed link is not.

## Consequences

- Framework coverage is reproducible from config plus sync, and reviewable as a
  code change.
- The matrix, confidence, and score can join on stable foreign keys instead of
  parsing config at render time.
- Adding a framework is a config change with a test, not a database edit.
- Stale canonical controls accumulate if config entries are removed and nobody
  prunes them; the sync command reports them on every run.
- Per-install customisation of the canonical catalogue is not supported yet. If
  it is needed, it belongs in a separate overlay table, not edits to derived
  rows.
- The catalogue is outcome-shaped (`CTRL-ACCESS-002`), not clause-shaped
  (`AC-2`). Mapping internal controls to imported framework clauses remains a
  curated crosswalk, documented per link.
