# Findings and remediation

A finding is a gap: a control test failed, a scanner found a weak configuration, a
CSET observation needs attention, or a person raised an issue. Remediation is the
work that follows. OpenGRC keeps the finding and a light remediation record; it
does not run a project plan.

## Finding fields

| Field | Meaning |
|---|---|
| Title | A short statement of the gap |
| Description | Condition, criteria, cause, effect and recommendation |
| Severity | Critical, high, medium or low |
| Status | Open, in progress, resolved or accepted |
| Source | Manual or automated check |
| Control | The control the finding concerns |
| Risk | The risk the finding contributes to, if one exists |
| Asset | The affected asset |
| Owner | The person accountable for remediation |
| Due date | When the remediation is expected |
| Resolution notes | What changed, or why the risk was accepted |

## The finding-to-risk link

A finding can be linked to a risk. The link is a relationship, not a closure:

- Linking does not resolve the finding.
- Resolving the finding does not close the risk.
- A risk can exist before a finding, and a finding can exist before a risk.
- The risk treatment is still a risk decision; the finding is the evidence that
  prompted it.

Use the link when a finding changes the likelihood or impact of an existing risk,
or when it is the reason a new risk is raised. Do not create a duplicate risk for
every scanner result.

## Lifecycle

| Status | Meaning |
|---|---|
| Open | Raised, not yet being worked |
| In progress | Someone owns the work and has started |
| Resolved | The gap was fixed and verified |
| Accepted | The risk was accepted; the finding stays visible |

An accepted finding is not fixed. It remains in the register so the acceptance can
be reviewed and expired like any other risk decision. Link it to the risk
acceptance record where one exists.

## Remediation without a project plan

OpenGRC does not have tasks, milestones or dependencies. The minimum record is:

1. An owner and a due date on the finding.
2. A description that states the recommendation, not only the problem.
3. A linked risk where the finding affects one.
4. Resolution notes that say what changed and how it was verified.
5. Evidence in the audit item, document store or machine evidence record.

For a large remediation, use an external project tracker and put the ticket id in
the notes. Do not create a second task list inside the GRC tool.

## Automated findings

Automated checks open or update findings using a dedupe key. Re-running a check:

- updates the existing finding and increments `occurrences`;
- refreshes `last_seen_at`;
- does not reopen an accepted finding;
- does not auto-resolve a finding when the check passes; a person verifies the
  fix and resolves it.

That last rule is deliberate. A scanner miss is not proof of a fix.

## Evidence

For an audit, keep:

- the finding record and its history;
- the linked risk and the treatment decision;
- the due date and whether it was met;
- resolution notes and the verification;
- the report or test that raised the finding.

## Limits

- No task list, milestone or dependency tracking.
- No automatic notification on assignment or overdue.
- No multi-step approval chain.
- No automatic closure from a passing check.
- No separate remediation register; the finding is the record.
