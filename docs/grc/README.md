# GRC capability map and maturity assessment

This section documents how OpenGRC is meant to be operated as a GRC system, not
just how it is installed. It is written for three audiences: the team running
their own programme, the people authoring training material on top of it, and
auditors who need to understand what the tool does and does not evidence.

## How to read the maturity levels

| Level | Name | Test |
|---|---|---|
| 1 | Recorded | The object exists and has an owner |
| 2 | Linked | It is connected to the standard, control, risk, or asset it concerns |
| 3 | Evidenced | There is an artifact a stranger could inspect |
| 4 | Tested | Someone independent checked it, on a schedule, and recorded the result |
| 5 | Improved | Failures feed back into a decision with a date and an owner |

Level 5 is rare and expensive. Most healthy programmes live at 3–4 for their
important controls and 2 for the rest.

## Capability map

| Capability | Supported today | Level the tool enables | Evidence |
|---|---|---|---|
| Framework and control library | Yes: 10 public standards, provenance register | 3 | `docs/grc/framework-catalog.md` |
| Control implementation and ownership | Yes: status, effectiveness, owner, test procedure | 4 | `Implementation` model |
| Risk register | Yes: inherent + residual 1–5 scoring, treatment, mitigations | 4 | `docs/grc/risk-methodology.md` |
| Policies and exceptions | Yes: lifecycle, acknowledgements, exceptions with expiry | 4 | `Policy`, `PolicyException` |
| Audits and testing | Yes: audits, audit items, effectiveness, notes | 3 | `docs/grc/audit-playbook.md` |
| Evidence management | Partial: attachments on audits and data requests | 3 | `docs/grc/evidence-standard.md` |
| Vendor risk (TPRM) | Yes: inventory, questionnaires, scoring, documents | 4 | `Vendor`, `Survey*` |
| Asset and application inventory | Yes: assets, applications, taxonomy, risk links | 3 | `Asset`, `Application` |
| Findings and remediation | Partial: audit item effectiveness + mitigations | 2 | Gap 2 |
| Control testing cadence | Partial: test procedure, no scheduled re-test | 2 | Gap 1 |
| Metrics and reporting | Partial: widgets, exports, AI summaries | 2 | Gap 3 |
| Regulatory mapping | Partial: cross-framework mapping is manual | 2 | Gap 4 |

## Gaps, stated plainly

These are known limitations. They are listed here so nobody discovers them during
an audit.

1. **No scheduled control testing.** Implementations record a test procedure and
   effectiveness, but not *when* they were last tested or when the next test is
   due. Until that exists, cadence lives in a calendar or a checklist. Recommended
   fields: `last_tested_at`, `next_review_at`, plus a stale-control report.
2. **Findings are not first-class.** A failed audit item carries an effectiveness
   value and notes; remediation is a mitigation on a risk. There is no finding
   object with severity, owner, due date, and status. Recommended next feature.
3. **Evidence is scoped to audits and data requests.** An implementation can be
   described and tested, but a file cannot be attached directly to it. Evidence
   has to live on an audit item or a data request response.
4. **Crosswalks are manual.** Two standards can both be imported, but there is no
   mapping object between their controls, so coverage across frameworks is a
   spreadsheet exercise.
5. **No appetite thresholds in the product.** The 5×5 model produces a level;
   whether that level is acceptable is a programme decision recorded in documents,
   not enforced by a setting.
6. **No attestation workflow for control owners.** Owners are recorded; a periodic
   "I confirm this is still true" event is not.

Each gap has a documented workaround. Say so in your own programme documentation
rather than pretending the tool covers it.

## Minimum viable programme

If you are starting from zero, this sequence reaches level 3 across the core in
about a quarter:

1. Import one framework. Do not import five.
2. Define scope and write it down (`Program.scope_status`, plus a document).
3. Create implementations for the controls in scope, with owners.
4. Run a risk assessment for the same scope and link risks to controls.
5. Create one audit with items for your highest-risk controls.
6. Attach evidence to every completed item. If you cannot, the item is not done.
7. Write the two policies you actually enforce, and retire the rest.
8. Review quarterly: stale owners, open risks, failed items, expiring exceptions.

The playbook for the full operating rhythm is in `docs/grc/program-playbook.md`.
