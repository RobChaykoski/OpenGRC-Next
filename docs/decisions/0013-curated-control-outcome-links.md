# 0013 — Control-to-canonical links are curated and many-to-many

Status: accepted
Date: 2026-11-18

## Context

The canonical catalogue (ADR 0012) holds outcomes such as "least-privilege
access control". Imported controls hold published clauses such as `AC-2` or
`A.8.8`. Coverage, the matrix, and the assurance score all need to know which
clauses an organisation treats as fulfilling which outcome.

The tempting shortcut is to match on code prefixes or title similarity. That
produces a full-looking graph built on guesses. A wrong link is worse than a
blank: it hides a coverage gap and puts a claim in front of an auditor that
nobody reviewed. Imported catalogues also differ in shape — a single clause can
serve several outcomes, and an outcome is normally met by several clauses.

## Decision

1. Links live in `control_canonical_outcome`, many-to-many between `controls` and
   `canonical_controls`.
2. Every link records its origin in `source`: `config` for the curated map in
   `config/canonical_control_links.php`, `manual` for links made in the UI.
3. `opengrc:catalog:link` applies the curated map. It replaces `config` links for
   each framework the map covers, never touches `manual` links, and fails
   atomically on an unknown canonical outcome.
4. Nothing auto-matches. Unlinked is a valid, visible state, labelled as a
   coverage gap to review rather than a failure.
5. The shipped map is deliberately partial and says so. Large catalogues such as
   NIST SP 800-53 are not bulk-mapped.

## Consequences

- Coverage can be computed without inventing precision, and the gap filter is
  actionable.
- Someone must review links; an empty graph is the honest starting point, not a
  bug.
- A clause that serves several outcomes can say so, which single-column designs
  cannot.
- Manual work survives re-running the curated sync; config-owned work is
  reproducible.
- Curated maps need maintenance when a framework version changes. The command
  reports identifiers that no longer match instead of silently dropping them.
- Reports must distinguish "no link" from "no evidence". They answer different
  questions.
