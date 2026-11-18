# Assurance matrix

The matrix answers one question: **for this framework, which outcomes does it
require, and what currently supports them?**

Open **Foundations → Assurance matrix**. Choose a framework; the page lists
every canonical outcome that framework requires, with:

| Column | Meaning |
|---|---|
| Code, Outcome | The canonical control (`CTRL-ACCESS-001`) and its name |
| Category, Priority | The catalogue definition; priority reflects the scoping engine's ranking |
| Automation | Whether evidence for this outcome can arrive without a human |
| Controls | Distinct imported controls linked to the outcome, excluding deleted ones |
| Policies | Distinct policies or documents linked directly or through a linked control |
| Evidence | Distinct evidence records whose control is linked |
| Status | What is missing, if anything |

## Status is a review prompt, not a score

| Status | Meaning |
|---|---|
| **Linked + evidenced** | At least one control is linked and at least one of those controls has evidence, or a validation result |
| **Evidence missing** | A control is linked, but no evidence or validation exists |
| **Evidence stale** | The latest evidence or validation is older than the outcome's frequency allows |
| **Validation failing** | The latest validation result for a linked control failed |
| **No reviewed control** | Nobody has linked a control to this outcome yet |

None of these states says the organisation is compliant. "Linked + evidenced"
means a person reviewed the link and a measurement exists. It does not say the
measurement passed, the control is effective, or the framework is satisfied. A
failed validation is reported on the control and in findings, not hidden by a
green badge here.

## Assurance score

Above the table, the page shows a score for the selected framework. It is a
weighted summary of review and evidence quality, with every component visible:

| Component | Weight | Computed from |
|---|---|---|
| Reviewed | 25% | Share of required outcomes with a linked control |
| Evidence | 30% | Share with an evidence record or validation result |
| Quality | 20% | Average best confidence among evidenced outcomes |
| Automation | 15% | Share whose best evidence is API-collected or a validation |
| Monitoring | 10% | Share with an enabled validation rule on a linked control |

Confidence is derived from how evidence arrived, never typed in:

| Basis | Confidence |
|---|---|
| Validation result | 100 |
| API or connector evidence | 80 |
| Manual or unknown evidence record | 50 |
| Documentation only (a linked policy) | 20 |
| Nothing | 0 |

The best available basis for each outcome wins. An outcome supported only by a
policy is therefore capped at 20 until evidence of operation exists.

The score is framework-specific and has no cross-framework total. A framework
with no outcomes has no score. **It is not a compliance percentage**: read the
components before acting on it.

### Trend

```bash
php artisan opengrc:assurance:snapshot
```

Stores one row per framework per day, including the components and counts.
Running it twice on the same day updates that day's row rather than duplicating
it. It is scheduled daily at 06:15. The page shows a 30-day delta once at least
two snapshots exist; before that it says so instead of inventing history.

## Gaps filter

**Gaps only → Only outcomes needing review** keeps outcomes with no linked
control, no evidence, stale evidence, or a failing validation. This is the
working list for a GRC programme: it is expected to be non-empty, especially on
first use.

Do not clear it by guessing links. A guessed link makes the row look green and
hides a real gap. See [control-catalogue-linking.md](control-catalogue-linking.md).

## What the counts exclude

- Soft-deleted controls, policies and evidence tied to deleted controls.
- Evidence with no control. It cannot support an outcome, because evidence
  inherits outcomes through its control (ADR 0014).
- Duplicates. A policy linked directly *and* through a control counts once.

## What the page does not do

- It does not import frameworks. Use `php artisan opengrc:framework-import`.
- It does not sync the catalogue. Use `php artisan opengrc:catalog:sync`.
- It does not apply curated links. Use `php artisan opengrc:catalog:link`.
- It does not edit anything. Links are made on the control and policy pages.

## Related

- `docs/ROADMAP-continuous-assurance.md` — where this sits in the roadmap
- `docs/grc/control-catalogue-linking.md` — what a link means
- ADR 0012, ADR 0013, ADR 0014 — catalogue, links, and evidence inheritance
