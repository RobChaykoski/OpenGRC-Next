# Programme playbook

How a GRC programme actually runs in OpenGRC: who does what, how often, and what
artifact proves it happened. This is also the curriculum backbone for the
hands-on training track — each role and cadence is a teachable competency.

## Roles

| Role | Owns | Typical work |
|---|---|---|
| Programme lead | The programme, residual risk acceptance | Sets scope, reviews risk, runs quarterly review, signs reports |
| Control owner | Implementations for their domain | Maintains implementation detail, supplies evidence, remediates |
| Risk owner | Specific risks | Scores, treats, monitors, reports change |
| Auditor | Tests and findings | Scopes audits, tests items, writes findings |
| Policy owner | Policies and exceptions | Drafts, reviews, retires, handles exceptions |
| Vendor manager | Vendor inventory and assessments | Onboards, assesses, monitors, offboards |
| Asset owner | Asset records and classification | Keeps inventory accurate, assigns criticality |
| Admin | Users, roles, configuration | Access control, settings, integrations |

One person can hold several roles in a small team. The rule that must not bend:
**the person who owns a control does not attest to its effectiveness.**

## Cadence

| Frequency | Activity | Artifact |
|---|---|---|
| Continuous | Add risks, incidents, vendor changes; attach evidence as work happens | Records and attachments |
| Weekly | Triage new high risks; check failed jobs and imports | Risk notes, queue status |
| Monthly | Review high and very high risks; check remediation dates; review vendor assessments due | Risk review note |
| Monthly | Access review for production systems | Export + reviewer attestation |
| Quarterly | Full risk re-score; control walkthrough; policy review; exception expiry check | Quarterly review pack |
| Quarterly | Control testing sample (the audit cycle) | Audit items + evidence |
| Annually | Re-baseline impact bands; re-assess top risks; restore test; incident exercise; framework review | Updated methodology, reports |
| On change | Re-assess affected risks, update implementations, re-test controls | Change record linked to controls |

## Quarterly review agenda

Sixty minutes, six slides, decisions recorded:

1. **Scope and coverage.** Controls in scope, tested this quarter, percentage.
2. **Risk position.** Movement of high and very high risks; acceptances expiring.
3. **Control health.** Failed items, overdue remediation, stale evidence.
4. **Exceptions.** Active policy exceptions, expiry, compensating controls.
5. **Third parties.** Vendors overdue for assessment; material changes.
6. **Decisions.** What was accepted, what was funded, what was deferred, and by whom.

If a quarter ends with no decisions, the review was a status meeting.

## Metrics that mean something

| Metric | Definition | Healthy direction |
|---|---|---|
| Control coverage | Implementations with an owner and a known status ÷ controls in scope | Up |
| Evidence freshness | Controls with evidence inside the freshness window ÷ implemented controls | Up |
| Tested this period | Audit items completed with evidence in the period ÷ items in scope | Up, then stable |
| Open high risks | High + very high risks without a dated plan | Down |
| Remediation age | Median age of open failed findings | Down |
| Exception debt | Active exceptions past expiry | Zero |
| Vendor review lag | Vendors past their review date | Down |
| Re-test pass rate | Re-tested items that now pass ÷ re-tested items | Up |

Avoid "number of audits", "number of policies", and "number of controls imported".
They measure activity, not assurance.

## Onboarding a new team member

Day one, they should be able to:

1. Find the scope document and one framework.
2. Open a control, read the implementation, and see its evidence.
3. See the top risks and who owns them.
4. Know the cadence and where the calendar lives.
5. Know they must not attest to their own control.

If any of those takes more than ten minutes, the programme has a documentation
problem, not a training problem.

## Scaling from one person to a team

| Team size | Practical structure |
|---|---|
| 1 | All roles, external pen test, quarterly self-audit with a peer reviewer |
| 2–5 | Separate control owner and auditor; shared risk register; monthly review |
| 6–15 | Domain control owners; dedicated programme lead; quarterly audit cycle |
| 15+ | Segregated audit function, formal exception process, vendor risk programme |

Do not copy the 15-person structure into a 3-person team. The controls that matter
are the same; the ceremony is not.

## Relationship to training

The training track teaches this playbook by doing it:

| Playbook element | Training equivalent |
|---|---|
| Scope and criteria | Scenario setup with a defined system boundary |
| Control implementation | Learner writes the implementation for a real control |
| Evidence standard | Learner produces an artifact that passes the five-minute test |
| Audit testing | Learner tests a peer's control and raises a finding |
| Risk treatment | Learner scores the resulting risk and proposes treatment |
| Quarterly review | Cohort debrief with decisions recorded |

See `.opencode/skills/opengrc-training-scenario-authoring/SKILL.md` for how to
package an exercise, and `docs/integrations/opencyberrange-grc-training.md` for how
technical labs supply the evidence.
