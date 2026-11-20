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
| Software supply chain | Yes: CycloneDX SBOM import, OSV vulnerability findings, fixed versions and due dates | 3 | `docs/integrations/sbom-osv.md` |
| Guidance and advisories | Yes: publisher/reference/version, adoption status, owner, review and control mapping | 4 | `docs/grc/guidance-register.md` |
| Evidence management | Partial: attachments on audits and data requests; hashed, append-only machine evidence per control | 3 | `docs/grc/evidence-standard.md` |
| Continuous control monitoring | Yes: sources, jobs, rules, validations, automatic findings | 4 | `docs/decisions/0009-continuous-control-monitoring.md` |
| Document templates | Yes: private originals, encrypted extracted text, classification access | 2 | `docs/grc/document-templates.md` |
| Policy readiness | Yes: readiness register with separate design/operating/evidence status | 3 | `docs/grc/policy-readiness.md` |
| Vendor risk (TPRM) | Yes: inventory, questionnaires, scoring, documents; no nth-party graph | 3 | `Vendor`, `Survey*` |
| Asset and application inventory | Yes: assets, applications, taxonomy, risk links | 3 | `Asset`, `Application` |
| Findings and remediation | Yes: triage queue, severity, owner, due date, status; automated from validation | 3 | `Finding` |
| Control testing cadence | Yes: last/next test dates, overdue filter; no automatic reminders | 3 | Gap 1 |
| Metrics and reporting | Partial: widgets, exports, AI summaries; no heat map or scorecard builder | 2 | Gap 3 |
| Regulatory mapping | Partial: cross-framework mapping is manual | 2 | Gap 4 |
| Regulatory change monitoring | Not built: no feed of new or amended requirements | 1 | Gap 7 |
| Collaboration and workflow | Partial: three roles, assignments, comments, exports; no approval engine | 2 | Gap 8 |

## Gaps, stated plainly

These are known limitations. They are listed here so nobody discovers them during
an audit.

1. **Testing cadence is recorded, not enforced.** Implementations have
   `last_tested_at` and `next_test_at`, and the list can filter overdue controls.
   Nothing sends a reminder, and completing an audit item does not stamp the
   dates; someone must update them. Recommended next step: a scheduled command
   that reports overdue tests and, optionally, a notification channel.
2. **Findings are first-class; remediation is lightweight.** A finding has
   severity, owner, due date, status, and resolution notes, with a triage screen
   and automated raising from validation. There is no separate remediation plan
   with tasks, milestones, or a link to a risk treatment. Use the notes field and
   a linked risk until that lands.
3. **Human evidence is scoped to audits and data requests.** Machine evidence can
   reference a control directly, but a person cannot attach a file to an
   implementation. Until that exists, upload human evidence to an audit item and
   record machine evidence through a connector.
4. **Crosswalks are manual.** Two standards can both be imported, but there is no
   mapping object between their controls, so coverage across frameworks is a
   spreadsheet exercise.
5. **No appetite thresholds in the product.** The 5×5 model produces a level;
   whether that level is acceptable is a programme decision recorded in documents,
   not enforced by a setting.
6. **No attestation workflow for control owners.** Owners are recorded; a periodic
   "I confirm this is still true" event is not.
7. **No regulatory change feed.** Requirements are imported when you choose to
   import them. There is no subscription that tells you a standard was revised,
   and no diff against the version you assessed against.
8. **Approvals are per record, not a workflow engine.** Resources have approve,
   edit, and delete actions with role checks. There is no multi-step approval
   chain, delegation, or notification on assignment, and no per-field conditional
   routing. Use the audit trail and comments for accountability.
9. **Nth-party risk is not modelled.** A vendor's own subcontractors are not a
   first-class relationship. Record them in the vendor record or as separate
   vendors with a parent note until a supply-chain graph exists.
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
| Guidance and advisory register | `docs/grc/guidance-register.md` |
| Evidence and integrity | `docs/grc/evidence-standard.md` |
| Policy readiness register | `docs/grc/policy-readiness.md` |
| Exception lifecycle and register | `docs/grc/exception-management.md` |
| Policy acknowledgement and coverage | `docs/grc/policy-acknowledgements.md` |
| Document templates | `docs/grc/document-templates.md` |
| Continuous monitoring | `docs/decisions/0009-continuous-control-monitoring.md` |
| Data loss prevention and AI limits | `docs/security/data-loss-prevention.md` |
