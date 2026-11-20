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
| Framework and control library | Yes: 20 registered standards, provenance register, NIST generated from OSCAL | 3 | `docs/grc/framework-catalog.md` |
| Control implementation and ownership | Yes: status, effectiveness, owner, test procedure, last/next test dates | 4 | `Implementation` model |
| Risk register | Yes: inherent + residual 1–5 scoring, owner, category, treatment, mitigations, dated acceptance with expiry | 4 | `docs/grc/risk-methodology.md`, `docs/grc/risk-acceptance.md` |
| Policies and exceptions | Yes: lifecycle, versioned acknowledgements, scoped exceptions with expiry, global register and daily expiry enforcement | 4 | `Policy`, `PolicyException`, `docs/grc/policy-acknowledgements.md`, `docs/grc/exception-management.md` |
| Audits and testing | Yes: audits, items, effectiveness, notes, attachments, findings | 3 | `docs/grc/audit-playbook.md` |
| Assurance and attestations | Yes: SOC/ISO/PCI/CMMC report register, outcome, period, expiry and review | 3 | `docs/grc/assurance-reports.md` |
| Privacy requests | Yes: GDPR/CCPA/PIPEDA/FERPA register, regime clock, verification, extension, overdue enforcement | 4 | `docs/grc/privacy-requests.md` |
| Non-human identities | Yes: service accounts/tokens/certificates, owner, scope, rotation, expiry, review; optional OpenBao metadata sync | 4 | `docs/grc/non-human-identities.md` |
| Software supply chain | Yes: CycloneDX SBOM import, OSV vulnerability findings, optional deps.dev/Scorecard enrichment | 3 | `docs/integrations/sbom-osv.md`, `docs/integrations/deps-dev.md` |
| Guidance and advisories | Yes: publisher/reference/version, adoption status, owner, review and control mapping | 4 | `docs/grc/guidance-register.md` |
| Evidence management | Yes: human evidence on implementations, audits and data requests; hashed, append-only machine evidence per control | 3 | `docs/grc/evidence-standard.md` |
| Continuous control monitoring | Yes: sources, jobs, rules, validations, automatic findings | 4 | `docs/decisions/0009-continuous-control-monitoring.md` |
| Document templates | Yes: private originals, encrypted extracted text, classification access | 2 | `docs/grc/document-templates.md` |
| Policy readiness | Yes: readiness register with separate design/operating/evidence status | 3 | `docs/grc/policy-readiness.md` |
| Vendor risk (TPRM) | Yes: inventory, questionnaires, scoring, documents, subcontractor register; no nth-party graph | 3 | `Vendor`, `docs/grc/vendor-subcontractors.md` |
| Asset and application inventory | Yes: assets, applications, taxonomy, risk links | 3 | `Asset`, `Application` |
| Findings and remediation | Yes: triage queue, severity, owner, due date, status, risk link; automated from validation | 3 | `docs/grc/findings-and-remediation.md` |
| Scanner assessment import | Yes: SARIF 2.1, report hash, deduplicated findings, application/asset link | 3 | `docs/integrations/assessment-sarif.md` |
| CSET assessment import | Yes: PCII-scrubbed JSON, counts, observations as findings; no CSET score or question bank | 3 | `docs/integrations/cset.md` |
| Control testing cadence | Yes: last/next test dates, overdue filter and `opengrc:controls:testing` report; no automatic reminders | 3 | `docs/grc/control-testing.md` |
| Control owner attestations | Yes: per-implementation request, owner statement, decline and expiry; no campaign engine | 3 | `docs/grc/control-attestations.md` |
| Metrics and reporting | Partial: widgets, exports, AI summaries and a risk heat map; no scorecard builder | 2 | `docs/grc/risk-methodology.md` |
| Regulatory mapping | Partial: canonical outcome links, plus a reviewed control-to-control crosswalk register; no automatic matching | 3 | `docs/grc/control-crosswalks.md` |
| Regulatory change monitoring | Partial: a human-maintained change register with impact assessment and affected frameworks; no feed or diff | 3 | `docs/grc/regulatory-changes.md` |
| Collaboration and workflow | Partial: three roles, assignments, comments, exports and ordered multi-step approval requests; no per-field routing or delegation | 3 | `docs/grc/approval-workflows.md` |
| Access review campaigns | Yes: account snapshot, reviewer attest/revoke, completion rule, evidence | 4 | `docs/grc/access-reviews.md` |
| Exercises and tabletops | Yes: type, source, objectives, participants, findings, actions, evidence | 3 | `docs/grc/exercises.md` |

## Gaps, stated plainly

These are known limitations. They are listed here so nobody discovers them during
an audit.

1. **Testing cadence is recorded, not enforced.** Implementations have
   `last_tested_at` and `next_test_at`, and the list can filter overdue controls.
   `php artisan opengrc:controls:testing` reports due and overdue tests, but
   nothing sends a reminder, and completing an audit item does not stamp the
   dates; someone must update them. Recommended next step: connect the report to
   the notification channel the programme already watches.
2. **Findings link to a risk; remediation is otherwise lightweight.** A finding
   has severity, owner, due date, status and resolution notes, with a triage
   screen and automated raising from validation. It can be linked to the risk it
   contributes to. There is still no separate remediation plan with tasks,
   milestones or dependencies. Use the due date, notes and linked risk until that
   lands, or keep the plan in an external tracker and record the ticket id.
3. **Human evidence is direct on implementations.** A file can be attached to an
   implementation, hashed by the evidence ledger and kept private. Audit items and
   data request responses remain separate paths for fieldwork evidence. There is
   no per-file expiry or retention rule beyond the ledger; the programme still
   decides freshness.
4. **Crosswalks are reviewed, not automatic.** Two standards can both be
   imported; canonical outcomes link their controls, and a reviewed
   control-to-control crosswalk record exists for pairs where the outcome is too
   coarse. Nothing matches controls by title or similarity, and a crosswalk does
   not close a coverage gap or prove equivalence.
5. **Appetite is a display threshold, not an enforcement rule.** An admin can set
   a global score threshold under **Settings → Risk settings**; the heat map
   outlines risks at or above it. It does not block a risk, assign treatment, or
   support per-category appetite. Record the real appetite in the risk treatment
   and acceptance fields.
6. **Attestations are per implementation, not a campaign.** An owner can be asked
   to confirm an implementation and can attest or decline, with a statement and
   expiry. There is no batch launch, sampling, reminder or escalation workflow;
   the programme requests attestations one implementation at a time.
7. **No regulatory change feed.** Requirements and changes are recorded when a
   person finds them. The change register captures the source, impact and affected
   frameworks, but there is no subscription that tells you a standard was revised
   and no diff against the version you assessed against.
8. **Approvals are an ordered chain, not a workflow engine.** Multi-step
   requests, named approvers, rejection with skip-later-steps and an approver
   queue exist. There is no per-field conditional routing, delegation, reminder or
   notification on assignment. Use the audit trail and comments for
   accountability.
9. **Nth-party risk is a flat register, not a graph.** A vendor records its
   subcontractors, their service, country, personal-data access, risk rating and
   last review. There is no recursive supplier graph, no inherited score and no
   automatic review schedule. Add a fourth party as a separate vendor when you
   manage it directly; otherwise use the subcontractor register.
10. **Monitoring is scheduled, not streaming.** Connectors run on a schedule and
    write evidence after the fact. There is no event stream, and no near-real-time
    detection path; see ADR 0009 for the current contract.

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

## Further reading

| Topic | Document |
|---|---|
| Control mapping across frameworks | `docs/grc/framework-catalog.md`, `docs/grc/framework-scoping.md`, `docs/grc/control-catalogue-linking.md` |
| Risk scoring | `docs/grc/risk-methodology.md` |
| Risk acceptance and expiry | `docs/grc/risk-acceptance.md` |
| Audit and testing | `docs/grc/audit-playbook.md` |
| Assurance reports and attestations | `docs/grc/assurance-reports.md` |
| Privacy requests and clocks | `docs/grc/privacy-requests.md` |
| Non-human identities | `docs/grc/non-human-identities.md` |
| OpenBao secret metadata | `docs/integrations/openbao.md` |
| SBOM and OSV supply-chain evidence | `docs/integrations/sbom-osv.md` |
| SARIF scanner import | `docs/integrations/assessment-sarif.md` |
| deps.dev and Scorecard enrichment | `docs/integrations/deps-dev.md` |
| Observable enrichment boundary | `docs/integrations/observable-enrichment.md` |
| CSET assessment import | `docs/integrations/cset.md` |
| Guidance and advisory register | `docs/grc/guidance-register.md` |
| Evidence and integrity | `docs/grc/evidence-standard.md` |
| Policy readiness register | `docs/grc/policy-readiness.md` |
| Exception lifecycle and register | `docs/grc/exception-management.md` |
| Policy acknowledgement and coverage | `docs/grc/policy-acknowledgements.md` |
| Access review campaigns | `docs/grc/access-reviews.md` |
| Exercises and tabletops | `docs/grc/exercises.md` |
| Control test cadence and reporting | `docs/grc/control-testing.md` |
| Control owner attestations | `docs/grc/control-attestations.md` |
| Control crosswalks | `docs/grc/control-crosswalks.md` |
| Multi-step approvals | `docs/grc/approval-workflows.md` |
| Findings, risk links and remediation | `docs/grc/findings-and-remediation.md` |
| Vendor subcontractors (fourth parties) | `docs/grc/vendor-subcontractors.md` |
| Regulatory and standard changes | `docs/grc/regulatory-changes.md` |
| Document templates | `docs/grc/document-templates.md` |
| Continuous monitoring | `docs/decisions/0009-continuous-control-monitoring.md` |
| Data loss prevention and AI limits | `docs/security/data-loss-prevention.md` |
| DLP false-positive quality and targets | `docs/security/dlp-quality.md` |
