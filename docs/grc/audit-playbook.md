# Audit playbook

An audit is a bounded exercise with a scope, a period, and a set of questions. In
OpenGRC it is an `Audit` with `AuditItem` rows, each pointing at the object being
tested. This playbook describes how to run one so the output survives scrutiny.

## Lifecycle

```
scope -> criteria -> plan -> evidence requests -> fieldwork -> findings -> report -> closure
```

Most programmes skip closure. That is why the same findings reappear next year.

## 1. Scope

Write down, before fieldwork:

- What is included: systems, locations, processes, vendors
- What is excluded, and why
- The period under review
- The criteria: which standard or framework, which version

Record it in the audit description and, for formal audits, as an attachment. A
scope that lives only in email is not a scope.

## 2. Criteria and objective

Every audit item should test one criterion. Link it to the control it tests
(`AuditItem` uses a polymorphic `auditable` relation, so an item can point at a
control, an implementation, or another auditable object).

An item that cannot name its criterion is a question, not a test.

## 3. Planning and sampling

- Choose items that cover the highest residual risks first.
- Sample, do not census. For access reviews, sample 10–25 users across roles; for
  changes, sample changes across the period, including one emergency change.
- Record the sampling method. "We looked at some" is not a method.
- State the population size and the sample size, so coverage is defensible.

## 4. Evidence requests

Use data requests for anything the auditee must supply (`DataRequest`,
`DataRequestResponse`). Ask for a specific artifact with a date range, not "access
records".

Good request:

> Export of all accounts with administrative access to production as of
> 2026-10-31, including role assignment date and last login, in CSV.

Bad request:

> Evidence of access control.

## 5. Fieldwork

For each item, record:

- Applicability (applicable / not applicable / partially), with a reason
- Status (`WorkflowStatus`: not started, in progress, completed)
- Effectiveness (`Effective`, `Partially Effective`, `Not Effective`, `Not Assessed`)
- Auditor notes: what was tested, what was observed, what was not
- Evidence attachment

The notes field is the working paper. Write it so a reviewer who was not there
could repeat the test.

## 6. Findings

There is no dedicated Findings object yet (see `docs/grc/README.md`, gap 2).
Record a finding this way, consistently:

1. Set the item to `Not Effective` or `Partially Effective`.
2. In the auditor notes, state: condition, criteria, cause, effect, recommendation.
3. Create a risk or update the existing risk for the underlying exposure.
4. Create a mitigation with an owner and a date, and reference the audit item.
5. Re-test at the next audit and link the new item to the previous one in the notes.

That chain gives you severity, ownership, remediation, and re-test — even without
a first-class object.

## 7. Report

A useful audit report has:

- Scope and period, restated
- Criteria and version
- Method and sampling
- Results summary by effectiveness
- Findings with severity, owner, and due date
- Items that could not be tested, and why
- Management response and dates

Attach it to the audit record. The report is the artifact most likely to be asked
for later.

## 8. Closure

An audit closes when:

- Every item is completed or explicitly out of scope with a reason
- Every not-effective item has an owner and a dated remediation
- The report is attached
- The programme lead has reviewed the residual risk position

Then set the audit status to completed. Do not leave it open "for reference" — it
distorts every coverage metric you report.

## Roles

| Role | Responsibility | Must not |
|---|---|---|
| Audit manager | Scope, plan, schedule, report | Test their own control |
| Auditor | Execute items, record evidence | Accept verbal evidence as proof |
| Control owner | Supply evidence, remediate | Mark their own item effective |
| Programme lead | Accept residual risk, close the audit | Rewrite auditor notes |

Independence does not need a separate company for internal audits, but the person
who owns a control should not be the person who attests to its effectiveness.

## Item quality checklist

- [ ] One criterion per item
- [ ] Population and sample size recorded
- [ ] Applicability reason given when not applicable
- [ ] Notes describe the test performed, not the conclusion alone
- [ ] Evidence attached and within the freshness window
- [ ] Effectiveness set, not left as Not Assessed
- [ ] Failures have a linked risk and a dated mitigation
- [ ] Re-test scheduled

## Metrics

Report these, not "number of audits completed":

| Metric | Why |
|---|---|
| Items tested / items in scope | Coverage, not effort |
| Percentage effective by framework | The only number an auditor trusts |
| Open findings by age | Shows whether closure happens |
| Re-opened findings | Shows whether remediation was real |
| Evidence age distribution | Predicts next audit's pain |
| Untestable items | Reveals scope problems |
