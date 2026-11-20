# 0040 — DLP false-positive targets are programme targets, not a universal benchmark

Status: accepted
Date: 2026-11-20

## Context

DLP vendors publish false-positive claims, and practitioners quote "acceptable"
rates. Those numbers are not comparable: they depend on the identifier type, the
content population, the detector, the review definition and the business context.
A 2% rate for payment card numbers and a 2% rate for an HR keyword detector do not
mean the same thing.

Standards such as NIST SP 800-53, NIST SP 800-122, PCI DSS and GDPR require
monitoring, review and accuracy in context. They do not publish a single
acceptable DLP false-positive percentage.

A programme still needs a target and a way to tell whether it is meeting it.

## Decision

1. **A human review disposition is the unit.** True positive, false positive,
   benign/accepted or undetermined. Only false positives count against detector
   quality.
2. **False-positive rate = false positives / reviewed findings.** Undetermined
   findings are excluded until reviewed. Benign matches are real matches the
   programme accepts; they are not detector errors.
3. **The target belongs to the identifier type.** Targets live in
   `config/dlp.php` with a `reference` and a `minimum_sample`.
4. **The reference is a requirement or a reason, not a fake standard.** The target
   may cite a control requirement, a privacy principle or an internal precision
   goal. It must not claim that a standards body published the percentage.
5. **A minimum sample is required.** A type below its minimum is "insufficient
   data", never "on target". A zero-finding type is untested, not perfect.
6. **The metrics are aggregate only.** No content, matched values, file paths or
   per-person score. The review queue is admin-only.
7. **No automatic tuning or suppression.** A change to a detector is a reviewed
   code change, not a metric that silently adjusts itself.
8. **The metric is a quality measure, not a compliance certificate.** A good rate
   does not prove the detector catches the right things; a bad rate does not prove
   the control failed.

## Consequences

- The programme can show a defensible trend by identifier type instead of one
  blended number.
- Targets must be maintained. An unmaintained target becomes a stale claim.
- Small populations will sit at "insufficient data" for a long time. That is
  honest, not a failure of the page.
- Reviewing findings is work. A queue nobody reviews produces no metric.
- A detector can be tuned to lower false positives and miss more true positives.
  The page does not measure recall; pair it with a true-positive review and, where
  possible, a known test corpus.

## Alternatives considered

- **Adopt a vendor's "industry average".** Rejected: not comparable across types
  or populations, and not a control requirement.
- **One global false-positive target.** Rejected: hides the difference between a
  structured card number and a contextual HR term.
- **Count every finding as a false positive unless proven otherwise.** Rejected:
  it punishes unreviewed data and makes the queue the metric.
- **Auto-suppress a detector above target.** Rejected: silent loss of detection is
  worse than a noisy queue.
- **Store the matched value to improve tuning.** Rejected: the no-content rule is
  a security boundary; tuning uses labels, notes and a controlled test corpus.
