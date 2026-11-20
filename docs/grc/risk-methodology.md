# Risk methodology

OpenGRC stores inherent and residual risk using a 5×5 likelihood × impact model.
This document states the meaning of each dimension and level so that two people
scoring the same risk arrive at the same number. The code that classifies scores
is `app/Enums/RiskLevel.php`.

## Scoring

| | Impact 1 | 2 | 3 | 4 | 5 |
|---|---|---|---|---|---|
| **Likelihood 5** | 5 | 10 | 15 | 20 | 25 |
| **4** | 4 | 8 | 12 | 16 | 20 |
| **3** | 3 | 6 | 9 | 12 | 15 |
| **2** | 2 | 4 | 6 | 8 | 10 |
| **1** | 1 | 2 | 3 | 4 | 5 |

Score = likelihood × impact. Levels:

| Score | Level | Meaning |
|---|---|---|
| 1–4 | Very Low | Tolerable without treatment; monitor |
| 5–8 | Low | Treat if cheap; otherwise accept with a reason |
| 9–12 | Moderate | Requires a treatment decision and an owner |
| 13–17 | High | Treatment plan with a date; escalate to the programme lead |
| 18–25 | Very High | Stop-and-fix. Escalate immediately, document interim controls |

## Reading the heat map

The **Risk heat map** page shows two 5×5 matrices for active risks: inherent and
residual. A cell counts the risks at that likelihood and impact and links to the
filtered risk list.

- The colour is the score band, not an appetite decision. Whether a Very High risk
  is tolerable is recorded in the risk treatment and acceptance fields.
- An admin can set a global appetite threshold under **Settings → Risk settings**.
  Cells at or above it are outlined. The outline is a visual prompt; it does not
  block the risk or replace a per-category appetite statement.
- Inherent is the score before the controls you have recorded; residual is after.
  A small movement between the two matrices is a prompt to check whether the
  treatment is real.
- Inactive risks are excluded. A closed risk should not keep colouring the map.
- The page is a view, not a stored report. It reads the live register each time it
  is opened.

## Likelihood definitions

Score the likelihood of the event occurring **in the next 12 months**, given
current controls.

| Score | Definition |
|---|---|
| 5 | Expected: has happened here, or is standard attacker behaviour against this exposure |
| 4 | Likely: credible path exists; no control specifically prevents it |
| 3 | Possible: requires a mistake, a specific condition, or an insider |
| 2 | Unlikely: control reduces it substantially, but not to zero |
| 1 | Rare: requires multiple simultaneous failures |

## Impact definitions

Score the **worst credible outcome**, not the average. Use the highest dimension
that applies.

| Score | Financial | Operational | Regulatory / contractual | Reputation |
|---|---|---|---|---|
| 5 | Existential or > 10% revenue | Multi-day outage of a core service | Reportable breach, licence risk | National press, customer loss |
| 4 | Major budget impact | 1–2 day outage | Regulator enquiry, notification duty | Industry press |
| 3 | Absorbable | Degraded service for a day | Contractual finding, remediation plan | Customer escalations |
| 2 | Minor | Brief degradation | Internal finding | Internal only |
| 1 | Negligible | No user impact | None | None |

Adjust the financial bands to your organisation and record the bands in your
programme documentation. The point is consistency, not the specific numbers.

## Inherent vs residual

- **Inherent** is the score with no controls beyond the baseline everyone has
  (authentication, patching, backups in place).
- **Residual** is the score with current controls operating as designed.

Residual should not equal inherent. If it does, either the controls do not exist
or the assessment was not done. A residual score lower than inherent requires the
control to be named and linked — that is the audit trail.

## Treatment

`MitigationType` values map to standard treatment options:

| Value | Use when | Must record |
|---|---|---|
| Mitigate | A control will reduce likelihood or impact | Which implementation, and by when |
| Accept | The cost of treatment exceeds the exposure | Who accepted it, and the expiry of the acceptance |
| Transfer | Insurance or contract shifts the loss | The instrument and its limits |
| Avoid | The activity is stopped | What was stopped, and the date |
| Open | Not yet decided | Owner and decision date — "open" is not a plan |
| Exploited | The risk has materialised | Incident reference |

`Mitigation` records carry a description, strategy, and implementation date, and
are attached to the risk. Use one mitigation per action, not one blob per risk,
so progress is measurable.

## Risk appetite

Appetite is a programme decision, not a field. Write it as a sentence per level:

> We accept residual risks at Very Low and Low without escalation. Moderate
> requires the control owner's sign-off. High requires the programme lead. Very
> High is not accepted and must have a dated plan.

Put that statement in your governance documentation and reference it from the risk
register's front page. When a score crosses a threshold, the acceptance must be
traceable to a person.

The register records that decision as an **acceptance** on the risk: status,
accepted by, accepted on, expiry and rationale. Acceptance is separate from a
mitigation strategy. `opengrc:risks:acceptance` expires an acceptance when its
date passes; re-acceptance is a new decision. See
`docs/grc/risk-acceptance.md`.

## Ownership and category

Every risk beyond triage needs an **owner**: the person accountable for the
treatment decision and the next review, not the person who happened to type it
in. The owner is a user reference (`risks.owner_id`), so the register can be
filtered by person and the name stays correct after a rename. An unassigned risk
is a finding in itself.

Category and department answer different questions:

| Field | Answers | Example |
|---|---|---|
| Risk category | What kind of risk is this? | Cybersecurity, Compliance, Operational |
| Department | Where does it live? | Infrastructure, Finance, HR |
| Scope | What does it affect? | Enterprise, a programme, a system |

Categories are seeded as taxonomy terms: Strategic, Financial, Operational,
Compliance, Cybersecurity, Reputational, Environmental. Rename or extend them in
the taxonomy settings; do not encode a category into the risk title or the code.
A risk may genuinely span categories, but pick one primary category so counts
mean something.

## Cadence

| Frequency | Activity |
|---|---|
| Continuous | New risks added as they are identified; exploited risks linked to incidents |
| Monthly | Review very high and high risks; check mitigation dates |
| Quarterly | Full register review: re-score, close, confirm owners, check acceptances expiring |
| Annually | Re-baseline impact bands, re-assess the top ten, update appetite |

## Review checklist

- [ ] Every risk has a named owner.
- [ ] Every accepted risk has an acceptance record and an expiry.
- [ ] Every mitigated risk links to at least one implementation.
- [ ] Residual ≤ inherent, with the reducing controls identified.
- [ ] Very High risks have a dated plan and an escalation record.
- [ ] Risks linked to assets or vendors reflect their current criticality.
- [ ] Closed risks say why they closed (no longer applicable, treated, accepted).

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| Everything is Moderate | Scores used for prioritisation, not assessment | Re-score against the definitions above |
| Residual always equals inherent | Controls not named | Require the linked implementation to record residual |
| Register only grows | No closure criteria | Quarterly review closes treated and obsolete risks |
| No very high risks | Fear of escalation | Explicitly document that very high is expected and actionable |
| Treatment in a spreadsheet | Tool friction | Use one mitigation per action with a date |
