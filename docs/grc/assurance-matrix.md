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
| **Linked + evidenced** | At least one control is linked and at least one of those controls has evidence |
| **Evidence missing** | A control is linked, but no evidence has been collected for it |
| **No reviewed control** | Nobody has linked a control to this outcome yet |

None of these states says the organisation is compliant. "Linked + evidenced"
means a person reviewed the link and a measurement exists. It does not say the
measurement passed, the control is effective, or the framework is satisfied. A
failed validation is reported on the control and in findings, not hidden by a
green badge here.

## Gaps filter

**Gaps only → Only outcomes needing review** keeps outcomes with no linked
control, or with a linked control but no evidence. This is the working list for a
GRC programme: it is expected to be non-empty, especially on first use.

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
