# 0003 — Framework data ships as public-domain control sets with a provenance register

Status: accepted
Date: 2026-11-17

## Context

OpenGRC imports frameworks as control libraries. Some are public domain (NIST, US
regulations), some are free to use but restrict redistribution (CIS), some are
copyrighted and cannot be redistributed (ISO/IEC, AICPA), and some are free to
consume but not to bundle (PCI DSS).

A GRC product that ships copyrighted control text creates legal exposure for every
downstream user, including people self-hosting it. A product that ships nothing
forces every user to retype a framework, which produces inconsistent identifiers
and defeats the purpose of the tool.

## Decision

1. Ship control data only where the licence permits it. Public-domain sets are
   included in full; conditional sets are included with their terms recorded;
   copyrighted sets are **referenced, not bundled** (bundle metadata plus a source
   URL you must supply yourself).
2. Record provenance and licence for every framework in
   `config/framework_catalog.php` — authority, licence, redistribution status,
   source URL, source file, expected control count.
3. Validate the data against the register with
   `php artisan opengrc:framework-audit`, which fails on duplicate identifiers,
   empty controls, orphan controls, and count drift, and warns on licences that
   need review.
4. Publish the same information for humans in `docs/grc/framework-catalog.md`.

## Consequences

- Users get immediate value from NIST, CIS, HIPAA, DFARS and CMMC-adjacent sets
  without legal ambiguity.
- ISO 27001 and PCI DSS require the user to obtain the standard. That is a step,
  not a blocker, and it keeps the repository clean.
- SOC 2 TSC is flagged for review before redistribution. It stays available for
  internal use; commercial reuse needs a legal decision.
- Adding a framework now has a fixed checklist, so provenance questions are
  answered once per framework rather than once per customer.
- The audit command makes silent content drift visible: a seeder change that drops
  fifty controls fails CI.

## Alternatives considered

- **Ship everything.** Rejected: copyright exposure, and it transfers legal risk
  to self-hosters who cannot evaluate it.
- **Ship nothing, link everything.** Rejected: destroys the out-of-box experience
  and pushes identifier inconsistency onto users.
- **Ship converted text with modified identifiers.** Rejected: breaks the one
  thing auditors need — the framework's own identifiers.
