# 0025 — Assurance reports are a register, not a control catalogue

Status: accepted
Date: 2026-11-20

## Context

SOC 1, SOC 2, SOC 3, ISO/IEC 27001 certificates, PCI DSS AOCs and CMMC
affirmations are often treated as if they were frameworks. They are not. They are
**reports and attestations about controls**, produced by an auditor or certifying
body over a period and a scope.

A SOC 2 report is evidence that an independent auditor examined the Trust Services
Criteria for a system over a period. The criteria are the framework; the report is
the opinion. SOC 1 is an ICFR attestation, SOC 3 is a general-use report, and an
ISO certificate is a certification decision, not a control library.

The common failures are predictable: a PDF is stored in a shared drive, nobody
records the period or scope, an expired certificate is still cited in a customer
questionnaire, a qualified opinion is summarised as "SOC 2 passed", and the bridge
letter is lost. A register fixes that; a folder does not.

## Decision

1. **The register stores report metadata, not the report body or the standard
   text.** It records what was assessed, by whom, over which period, with what
   opinion and exceptions. The document stays in the private document store and is
   referenced by path and hash.
2. **The supported types are explicit.** SOC 1 Type 1/2, SOC 2 Type 1/2, SOC 3,
   ISO/IEC 27001, ISO/IEC 27701, PCI DSS AOC/ROC, CMMC affirmation and other.
   Adding a type is a deliberate change, not a free-text field.
3. **The minimum record is scope, period, provider, outcome and owner.** Issued,
   expiry, bridge letter, next review, exceptions, vendor and framework code are
   recorded where they apply.
4. **A report does not make a control effective.** It is evidence about a control
   environment. A qualified opinion, an exception or a missing bridge letter is a
   finding to review, not a green badge.
5. **Expiry is explicit and enforced.** A report with an `expires_at` is marked
   Expired by `opengrc:attestations:expire` when the date passes. A report with no
   explicit expiry stays Current until superseded or withdrawn; its period end and
   next review date still make its age visible.
6. **Status is a lifecycle, not a guess.** Current, Expired, Superseded and
   Withdrawn. A new report supersedes the old one; it does not overwrite it.
7. **Vendor reports link to the vendor.** The organisation's own report has no
   vendor. A vendor's SOC 2 report is TPRM evidence and the vendor record remains
   the owner of the relationship.
8. **SOC 1 and SOC 3 are not imported as control catalogues.** SOC 2 uses the
   Trust Services Criteria, which is already a framework in the catalogue. The
   register records the report, not a second copy of the criteria.
9. **Licensing stays upstream.** ISO, PCI DSS and AICPA report text are not
   redistributed. The register links to the customer's licensed copy; it does not
   bundle the standard or the report.

## Consequences

- An auditor can answer "what assurance do we have, for what scope, for which
  period, and is it still current?" from one screen.
- The register is only as good as the metadata entered. A report with no scope or
  period is a filing cabinet with extra steps.
- The expiry command covers explicit expiry, not the end of a covered period. A
  SOC 2 report with a period end but no expiry needs a review date; the register
  shows it, but the operator must act.
- Vendor assurance and the organisation's own assurance share one register, which
  keeps the vocabulary consistent but requires discipline in the vendor link.
- A future automated import of a report package can attach the reference and hash,
  but the opinion and exceptions still need a person to read them.

## Alternatives considered

- **Import SOC 1/3 as frameworks.** Rejected: they are report types, not control
  catalogues. It would create empty or duplicate controls and confuse coverage.
- **Store the report PDF as the record.** Rejected: no period, scope, opinion or
  expiry query; the document is not the register.
- **Rely on the vendor's trust center.** Rejected: the trust center is a
  disclosure surface, not the internal assurance record, and it does not hold the
  bridge letter or the internal review.
- **Treat a SOC 2 report as a control pass.** Rejected: an opinion is about the
  control environment and the period, and it can be qualified.
- **One generic document register.** Rejected: it loses the report type, outcome,
  period and review semantics that make assurance inspectable.
