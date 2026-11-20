# 0031 — Risk acceptance is a dated decision, not a mitigation note

Status: accepted
Date: 2026-11-20

## Context

OpenGRC's risk model already records a treatment strategy. "Accept" was one of
the `MitigationType` values, which meant a risk could be marked accepted without
recording who accepted it, when, why, or for how long. A mitigation record says
what was done; it does not say that a named person took accountability for the
residual exposure.

The risk methodology has always said an accepted risk needs an acceptance record
and an expiry. The product did not have the fields, so the statement lived in a
document and the register could not prove it.

## Decision

1. **Acceptance is a first-class field set on the risk.** It records the status,
   the acceptor, the acceptance date, the expiry and the rationale. It is separate
   from the mitigation strategy and from the risk status.
2. **An acceptance needs a named person, a date, a rationale and an end date.**
   The form requires them when the acceptance status is Accepted. An open-ended
   acceptance is not the default.
3. **Expiry is enforced.** `opengrc:risks:acceptance` marks an accepted risk past
   its expiry as Expired every day and reports acceptances expiring in the next
   30 days. It does not renew anything.
4. **Re-acceptance is a new decision.** Extending an acceptance means a new date,
   a new rationale and a named acceptor, not a silent date edit.
5. **Revocation is a status.** Revoked and expired acceptances stay readable.
   Deleting the risk destroys the evidence that the decision existed.
6. **Acceptance is not a mitigation.** A mitigation records an action; an
   acceptance records accountability for the residual risk that remains. A risk
   can have both.
7. **The acceptor is recorded independently of the owner.** Policy may require
   the acceptor to be independent of the risk owner; the register keeps both so
   the decision can be reviewed, even when the same person holds both roles.
8. **A closed accepted risk stays visible.** Closure does not erase the
   acceptance history; it is part of the risk record.

## Consequences

- The register can answer "who accepted this risk, when does that acceptance
  end, and what was the rationale?" without reading a meeting note.
- Acceptance becomes slightly slower and more defensible. A risk cannot be
  accepted by changing a dropdown and walking away.
- The daily command can mark an acceptance expired without re-opening the risk.
  The owner still needs to treat, re-accept or close it.
- The acceptor field is a user reference; a renamed or departed user does not
  change the recorded decision.
- The evidence is the acceptance record, not the mitigation. An auditor can
  inspect both and see the difference.

## Alternatives considered

- **Keep acceptance as a mitigation strategy only.** Rejected: it has no acceptor,
  date, rationale or expiry, so it cannot prove accountability.
- **Allow open-ended acceptance.** Rejected: an acceptance with no end date is a
  permanent waiver by neglect.
- **Auto-renew when the date passes.** Rejected: silent renewal removes the
  decision and defeats the control.
- **Delete expired acceptances.** Rejected: the audit trail and the reason for the
  decision disappear.
- **Require the acceptor to be the risk owner.** Rejected as a universal rule:
  some organisations allow owner acceptance, others require independence. Keep
  both roles explicit and let policy decide.
