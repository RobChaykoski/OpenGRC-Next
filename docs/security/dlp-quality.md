# DLP false-positive quality

A DLP programme is only useful if people trust the queue. The number of rules is
not a quality measure; the false-positive rate by identifier type is.

This page explains what OpenGRC measures, where the targets come from, and how to
read the result.

See ADR 0040 for the boundary.

## What is measured

A **reviewed finding** is a DLP finding where a person recorded a disposition:

| Disposition | Meaning | Counts as a false positive? |
|---|---|---|
| True positive | The detector was right; the content was sensitive | No |
| False positive | The detector should not have raised it | **Yes** |
| Benign / accepted | A real match the programme allows in context | No |
| Undetermined | Reviewed but not decided | No, excluded |

The rate for an identifier type is:

```text
false-positive rate = false positives / reviewed findings for that type
```

The window is the last 90 days. A finding that matches more than one identifier
type counts in each type's denominator; it is one finding in the overall total.

## Identifier types

The implemented types are the native recognisers plus the Presidio mappings:

| Category | Identifier types |
|---|---|
| Credential | AWS access key, private key, JSON Web Token, secret assignment, credential or crypto address |
| Financial | Payment card number, payment or bank account number |
| Government identifier | US SSN, Canadian SIN, government or medical identifier |
| Special category | Health or medical information, criminal or background-check data, biometric information, protected characteristic, special-category data |
| HR | HR or personnel data |
| Personal identifier | Email address, phone number, personal identifier |

The exact labels and targets are in `config/dlp.php` under `identifier_types`.
The labels map scanner output to a stable code so a wording change does not break
the trend.

## Targets

There is **no single industry-standard false-positive percentage** for DLP. A
vendor benchmark or a peer's number is not a control requirement. The target is a
programme target with a recorded reference, for example:

| Type family | Starting target | Why |
|---|---|---|
| Credential and private key | 1% | High-confidence pattern; a false positive is a tuning defect |
| Payment card, SSN, SIN | 2% | Structured identifier with a checksum or format constraint |
| Generic credential or government identifier | 2–5% | Broader entity mapping; more context needed |
| Special-category terms | 10% | Contextual language; some ambiguity is expected |
| HR and personnel terms | 15% | Contextual keyword detector; high noise is normal |
| Email and phone | 15% | Common business content; context decides |

These are starting points, not standards. Change a target only with a reason and
update the `reference` text. Setting every target to zero is not a quality
programme; it is a way to make the report look good while the queue is ignored.

## Minimum sample

A rate from two reviews is not a measurement. Each type has a minimum sample:

- fewer reviewed findings than the minimum → **Insufficient data**;
- at or above the minimum and rate ≤ target → **On target**;
- at or above the minimum and rate > target → **Above target**.

The page shows the reviewed count and the minimum next to it.

## How to review a finding

1. Open **Admin → System → DLP findings**.
2. Read the source, severity, action, detected labels and content hash. The
   content itself is not stored. Use the source record or hash if you need to
   inspect the original.
3. Choose the disposition.
4. Add a short note explaining the decision. Do not paste the sensitive value.
5. Save. The reviewer and review time are recorded.

Reviewing is an admin action. The finding's detection metadata is immutable; only
the disposition and notes change.

## Reading the page

Open **Discovery → DLP quality**:

- **Reviewed findings** — the size of the evidence base.
- **False positives** — the number that count against detector quality.
- **Overall rate** — useful context, not a substitute for the per-type view.
- **Types on target** — how many types have enough data and meet their target.
- **Insufficient data** — types that need more reviews before a claim is fair.

A type with no findings is not "0% false positives"; it is untested. A type with
five reviews is not "on target"; it is insufficient data.

## Improvement loop

When a type is above target:

1. Read the false-positive notes. Is the pattern too broad, or is the context
   being ignored?
2. Tighten the pattern or add a context signal in the detector, not only in the
   report.
3. Re-review a sample after the change.
4. Record the change and the date. Keep the old findings; do not rewrite history.
5. Check that the true-positive rate did not collapse. A detector that flags
   nothing has a perfect false-positive rate and no value.

## Limits

- No automatic tuning, suppression list or machine-learning feedback loop.
- No per-user or per-department score; the unit is the identifier type.
- No content, matched values or file paths in the metrics.
- No universal benchmark. The target and reference are local decisions.
- No sampling workflow; every reviewed finding counts.
- The page is a view, not a stored report. It reads the live data each time.
