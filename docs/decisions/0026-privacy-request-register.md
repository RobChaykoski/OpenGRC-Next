# 0026 — Privacy requests are a regime-aware register, not audit requests

Status: accepted
Date: 2026-11-20

## Context

OpenGRC already has a `DataRequest` record, but it is an **audit evidence
request**: it belongs to an audit or an audit item, and its lifecycle is about
collecting evidence for an auditor. It is not a data-subject access request.

Privacy laws have their own vocabulary and clocks. GDPR requires a response within
one month, extendable by two further months. CCPA/CPRA uses 45 calendar days,
extendable to 90 in total. PIPEDA uses 30 days with a permitted extension. FERPA
uses 45 days in many institutional procedures. A request can be access, deletion,
correction, portability, restriction, objection, opt-out of sale/share, or a limit
on sensitive personal information. Identity verification, denial reasons and
extensions are part of the record.

Reusing the audit request would give two different legal processes one status
field, one due-date meaning and one reporting vocabulary. That is how a privacy
programme loses its clock.

## Decision

1. **A separate `PrivacyRequest` record.** It is a register entry for a data
   subject or consumer request, not an audit evidence request. The two models do
   not share a table or a status enum.
2. **The regime sets the clock.** `regime` plus `received_at` computes `due_at`.
   An operator can override the date with a reason. An extension sets
   `extended_at` and records why; it is a decision, not a silent date change.
3. **Working clocks are explicit.** GDPR one month plus two; CCPA/CPRA 45 days
   plus 45, to 90 total; PIPEDA 30 plus 30; FERPA 45 by institutional policy;
   other 30. These are working defaults. Counsel confirms the exact method for the
   jurisdiction and the matter.
4. **Verification precedes disclosure.** The record tracks whether identity was
   verified, how, and when. A request can be denied for a documented reason; the
   reason is part of the record, not a free-text afterthought.
5. **Overdue is visible.** `opengrc:privacy:deadlines` marks open requests past
   their effective due date as Overdue every day and reports requests due in the
   next seven days. An overdue statutory request is a compliance event, not a
   quiet status.
6. **Data minimisation is a record rule.** Store a requester name only where it is
   needed, prefer a case reference over full contact details, and keep the request
   correspondence and response package in the privacy case store. The register
   holds references and hashes, not the response payload.
7. **One request per person and right.** Do not merge unrelated requests into one
   row to make the metrics look smaller. Related requests can reference each
   other; they still keep their own clock.
8. **The register supports metrics, not marketing.** Volume by regime and type,
   on-time rate, extension rate and denial rate are the useful measures. A closed
   request with no evidence reference is not a completed request.
9. **OpenGRC is the register, not the intake portal or the erasure engine.** A
   consumer-facing form, identity verification service, data discovery and
   deletion across systems are separate capabilities. The register can link to
   them and store the evidence reference.

## Consequences

- The privacy team gets one clock and one vocabulary per regime, and the audit
  team keeps its evidence-request process separate.
- The working day counts are approximations for GDPR's calendar month. Counsel
  must confirm the exact calculation and notice requirements.
- The register is only as good as the data entered. A request with no received
  date has no clock; a request with no evidence reference is not proven.
- The daily command can mark a request Overdue after the fact; it cannot undo the
  missed statutory deadline. The value is visibility and escalation.
- Storing requester details in the GRC database is a privacy decision. The
  contact-reference field is a deliberate nudge toward minimisation, not a
  guarantee that no personal data is stored.
- A future consumer portal or IdP integration is a separate decision; it does not
  belong in the register.

## Alternatives considered

- **Reuse `DataRequest`.** Rejected: it is tied to audits and evidence requests,
  and it has no regime, request type, verification or statutory clock.
- **A spreadsheet with a due-date column.** Rejected: no access control, no
  evidence link, no consistent clock and no overdue enforcement.
- **No deadline automation.** Rejected: the date becomes a suggestion, and the
  overdue request is discovered by the regulator or the requester.
- **Build a full privacy case-management suite.** Rejected for this slice: intake,
  identity verification, data discovery and deletion are separate systems. The
  register is the compliance record, not the whole operation.
- **Use one generic "request" model for audit and privacy.** Rejected: the two
  processes have different legal meaning, different clocks and different evidence;
  a shared status field hides both.
