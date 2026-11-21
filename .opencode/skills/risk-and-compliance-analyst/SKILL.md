---
name: Risk and Compliance Analyst
description: Use when assessing, scoring, quantifying, reviewing or designing risk and compliance work - risk registers, risk appetite, heat maps, KRIs, control assurance, audit findings, board reporting, scenario analysis, third-party risk, AI risk, cyber risk quantification, or any actuarial sanity check on risk numbers. Explains the methods, the arithmetic, the design requirements, and the failure modes that make a risk programme look mature while deciding nothing.
---

# Risk and Compliance Analyst

This is a review and design skill as much as an analysis skill. Load it before
changing a risk methodology, a heat map, a scoring model, an appetite statement,
a risk report, or a control-assurance process. It is deliberately opinionated:
the goal is a decision someone can defend, not a register that looks complete.

## Mandate

Make risk and compliance work decision-useful and honest:

- Connect every risk to an owner, a treatment, a control, a date, and an
  acceptance decision.
- Quantify what can be quantified, label what is judgement, and never let the
  two look the same.
- Test whether the numbers survive arithmetic, base rates, and a sceptical
  reader.
- Turn compliance obligations into testable controls and evidence, not a
  document pile.
- Say plainly when the data is too thin for the precision being displayed.

Basic actuarial literacy is in scope. Formal reserving, regulatory capital,
insurance pricing, pension work, and model validation for a regulated balance
sheet are not. Escalate those to a qualified actuary.

## First principles

1. **Risk is a distribution, not a point.** A score is a summary. Two risks with
   the same score can have different tails, velocities, and correlations.
2. **Likelihood and impact are questions about a time horizon.** State it. A
   12-month likelihood is not a five-year likelihood.
3. **Risk is not an issue.** An issue has happened. A risk may happen. Mixing
   them corrupts every count and every trend.
4. **Inherent and residual mean different control baselines.** Define the
   baseline. "Inherent" does not mean "no controls anywhere"; it means the
   exposure before the controls being assessed.
5. **A control is not effective because it exists.** It is effective when it is
   designed, operated, tested, and produces evidence. Design, operation, and
   evidence are three different statuses.
6. **Appetite is a decision rule, not a colour.** It says who may accept what,
   at which level, with what authority and expiry. A heat map threshold is a
   display of that rule, not the rule itself.
7. **Compliance is not risk management.** A control can be compliant and still
   fail to reduce the risk that matters. A risk can be tolerable without being
   compliant, but that is an explicit, documented exception.
8. **Aggregation is a model, not addition.** Summing ordinal scores or P99s
   produces a confident number with no meaning.
9. **Every score needs a rationale short enough to audit.** "Likely, major" is
   not a rationale. "Credible path via unpatched internet-facing VPN; no MFA;
   last test failed" is.
10. **The register is a living decision log.** History matters: who changed what,
    when, why, and against which methodology version.

## The GRC object chain

```
Obligation / risk driver
  -> Risk (event, cause, consequence, owner, horizon)
       -> Treatment (mitigate, accept, transfer, avoid)
            -> Control / implementation
                 -> Test / assurance activity
                      -> Evidence
                           -> Finding -> Remediation -> Re-test
```

A risk without an owner is an observation. A control without a test is an
intention. A finding without a re-test is a wish. A policy without an
acknowledgement and a review date is a document.

## Risk assessment methods

Choose the lightest method that supports the decision.

| Method | Good for | Watch |
|---|---|---|
| Structured judgement (Delphi, workshops) | Novel risks, no data, contested estimates | Anchoring, seniority bias, false consensus |
| Risk matrix | Triage, communication, screening | Rank reversal, ordinal arithmetic, colour theatre |
| Scenario analysis | Severe but plausible events, resilience | Scenario sprawl; no decision owner |
| Bow-tie | Cause-control-consequence mapping | Looks complete while controls are untested |
| Fault tree / event tree | Technical failure paths, dependencies | Requires engineering fidelity |
| STRIDE / attack paths | Security design review | Threat catalogue is not a risk assessment |
| Reference-class forecasting | Base rates for frequency and severity | Wrong reference class, survivorship bias |
| FAIR / OpenFAIR | Cyber loss quantification in business terms | Calibration quality; avoid single-point output |
| Monte Carlo | Aggregation, tails, correlation | Garbage in, confident garbage out |
| Stress / reverse stress | "What would break us?" | Not a probability statement |

A scenario must name: the event, the threat or trigger, the asset or process, the
consequence, the time horizon, and the existing controls. Without those, it is a
topic, not a scenario.

## Quantification without false precision

### Core identities

```
Annualised loss expectancy   ALE  = frequency (events/year) x mean severity
Single loss expectancy       SLE  = asset value x exposure factor
Probability of >=1 event     P    = 1 - e^(-lambda)      (Poisson frequency)
Annual loss exceedance       VaR_p = quantile p of the annual loss distribution
Tail expected shortfall      ES_p  = E[loss | loss > VaR_p]
```

- **ALE is an expectation, not a budget or a worst case.** Use it to compare
  treatments, not to promise a number to the board.
- **Use percentiles for tolerance and capital.** P50, P90, P99 and ES answer
  different questions. Say which one is being shown.
- **Do not sum percentiles.** The P99 of a portfolio is not the sum of the P99s
  of its parts.
- **Do not net insurance without the policy.** Net risk = gross loss minus
  recoveries, subject to retention, limit, exclusions, and reinstatement terms.

### Frequency and severity

- Frequency is usually modelled as Poisson or negative binomial when events
  cluster. `lambda` is events per year; convert explicitly from per-month or
  per-transaction rates.
- Severity is usually right-skewed. Lognormal is common for loss amounts:
  `median = e^mu`, `mean = e^(mu + sigma^2/2)`, `P90 = e^(mu + 1.2816 sigma)`.
- Heavy tails need Pareto or a mixture. For Pareto shape `alpha`, the mean is
  finite only when `alpha > 1`; the variance only when `alpha > 2`. If the tail
  is that heavy, report tail metrics and avoid a comforting average.
- With sparse data, use three-point estimates (low, most likely, high) and a
  PERT mean `(low + 4*most_likely + high) / 6`. State that it is a judgement.

### Worked example (illustrative, not a forecast)

Ransomware scenario: frequency `lambda = 0.4` per year; severity lognormal with
median `$250k` and `sigma = 1.2`.

```
Mean severity  = 250k * e^(1.2^2 / 2) = 250k * 2.054 ~ $514k
ALE            = 0.4 * 514k ~ $206k/year
P90 severity   = 250k * e^(1.2816 * 1.2) ~ $1.16M
```

The decision is not "spend $206k". It is: does a proposed control reduce
frequency, severity, or both; by how much; with what evidence; and what happens
to the P90 and the tail? A control that halves frequency but not severity has a
different risk profile from one that caps severity through backups.

### Calibration and uncertainty

- Ask for an 80% interval, then check the hit rate over many questions. Most
  people are overconfident: their 80% interval contains the truth far less than
  80% of the time.
- Record the basis: observed loss data, industry base rate, expert judgement, or
  scenario. Do not blend them silently.
- Prefer ranges and sensitivity ("ALE $120k–$340k; the driver is frequency") over
  a single decimal.
- A probability forecast is testable. Track it with a Brier score where volume
  justifies it; otherwise review calibration qualitatively.
- Re-baseline when the exposure changes: revenue, records, endpoints, vendors,
  architecture, threat landscape, regulation.

### Control efficacy

Express control effect as a reduction in frequency and/or severity, with the
evidence that justifies it.

| Control type | Usually reduces | Evidence |
|---|---|---|
| Preventive (MFA, patching, least privilege) | Frequency | Configuration export, test result, coverage metric |
| Detective (logging, monitoring, EDR) | Severity / time-to-detect | Alert test, coverage, mean time to detect |
| Corrective (backups, failover, IR) | Severity / duration | Restore test, exercise result, RTO/RPO |
| Transfer (insurance, contract) | Financial severity only | Policy limits, exclusions, recovery test |

Do not claim a residual reduction without naming the control, its test, and the
date. "Residual equals inherent" is often honest: no effective control exists.

### Aggregation, concentration, and correlation

- Independent risks can be combined by simulation. Positive correlation
  increases the tail; negative correlation can reduce it, but do not assume it.
- Shared dependencies are the usual source of correlated loss: one identity
  provider, one cloud region, one vendor, one backup system, one administrator.
- Count concentration explicitly: top vendor share, single points of failure,
  shared credentials, common code dependencies, geographic concentration.
- Parent and child risks must not both be counted in the same total. Define the
  aggregation level before adding.
- Diversification is a property of the portfolio, not a reason to ignore a
  business unit's tail.

## Risk matrix and heat map review

### Arithmetic sanity

A 5x5 `likelihood x impact` score is a communication device. It is not a
continuous measurement.

- Are the axes defined with anchors (probability ranges, dollar bands, outage
  durations), not just 1–5 labels?
- Is the multiplication rule documented? `5x2 = 10` and `2x5 = 10` are the same
  number but very different risks. A weighted or matrix-lookup rule may be
  better; whatever is chosen must be consistent and visible.
- Are the bands monotonic? Check every cell: a higher likelihood at the same
  impact must not fall into a lower band.
- Is the scale linear, logarithmic, or ordinal? Multiplying ordinal labels
  implies equal spacing that does not exist. Map to anchors before arithmetic.
- Does the same risk score differently under inherent and residual because the
  controls changed, or because the assessor changed?
- Is the time horizon the same for every risk?
- Are closed, inactive, accepted, and duplicate risks excluded or separately
  identified?
- Do the counts reconcile with the register? Empty cells are information.

### Distribution smells

| Smell | Likely cause | Test |
|---|---|---|
| Everything is Moderate | Scores used to prioritise, not assess | Re-score against anchored definitions |
| No Very High risks | Escalation fear or a compressed scale | Ask what event would score 5; check the top ten by loss |
| Residual always equals inherent | Controls not named or not assessed | Require a linked control and test for every reduction |
| A suspiciously tidy diagonal | Assessors copying the same pattern | Compare rationales; sample independently |
| Huge cell counts, few owners | Risk inventory, not risk management | Count owners and treatment dates, not rows |
| Heat map improves after a reorg | Re-baselining without a decision | Check methodology version and history |
| Tail risks disappear in aggregation | Averaging or summing scores | Aggregate distributions, not ratings |

### Visual and decision sanity

- State the decision the map supports: triage, appetite breach, board
  escalation, treatment prioritisation, or assurance coverage.
- Show the methodology version and assessment date. A live page without a
  version is a screenshot of drift.
- Show `n` per cell and a register count. Colour without counts hides thin data.
- Inherent and residual should be separately labelled, not overlaid in a way
  that implies a precise movement.
- The appetite threshold must match the written appetite statement and the
  authority levels. If the map outlines "High" but the statement accepts High
  with owner sign-off, the display is lying.
- Colour is not the only encoding: include band labels, scores, or patterns.
  Avoid red/green-only palettes; use a colour-blind-safe sequential or
  blue-orange scale. Test in greyscale and dark mode.
- Provide a table alternative with the same data, sortable and filterable, and
  link each cell to the filtered register.
- Show uncertainty where it exists: a range, a confidence marker, or a tooltip
  that says "score is a judgement; basis: scenario/industry data".
- Aggregate views must not leak records the viewer cannot open. Suppress small
  cells where re-identification or commercial sensitivity applies.
- Ask: "What would change on this page if the decision changed?" If nothing,
  it is decoration.

### Register hygiene checklist

- [ ] Every risk has an owner (a person, not a team alias).
- [ ] Every risk has a clear event/cause/consequence statement, not a topic.
- [ ] Every risk has a horizon and an assessment date.
- [ ] Every treatment has an action, a date, and a named accountable owner.
- [ ] Every accepted risk has an acceptor, rationale, expiry, and authority.
- [ ] Every residual reduction names the control and the test that supports it.
- [ ] Risks link to the assets, vendors, processes, and obligations they affect.
- [ ] Findings and incidents link back to risks; repeat events trigger re-score.
- [ ] Closed risks say why, and do not keep colouring the map.
- [ ] Duplicates, parent/child overlaps, and stale risks are resolved.
- [ ] The methodology version and any scale changes are recorded.
- [ ] A sample of scores can be defended by someone who was not in the room.

## Design input for risk screens

A risk screen is a decision interface, not a data entry form. Demand:

1. **The decision.** What is the user deciding here: accept, treat, escalate,
   close, re-score, approve, or investigate?
2. **The basis.** Assessment date, assessor, methodology version, data sources,
   confidence, and last review.
3. **The chain.** Risk -> treatment -> control -> test -> evidence -> finding.
   One click each way.
4. **The arithmetic.** Show likelihood, impact, score, band, and the rule. Do not
   show a score that cannot be reproduced.
5. **The history.** Previous scores, who changed them, why, and what evidence
   changed. Immutable audit trail, not an editable "notes" field.
6. **The uncertainty.** Ranges or confidence for quantified risks; a plain label
   for judgement-based scores.
7. **The authority.** Who may accept this level, until when, and what happens on
   expiry.
8. **The aggregation.** Does this roll up to a programme, entity, or board view;
   and is that roll-up valid?
9. **The access.** Aggregate views must respect record-level permissions.
10. **The exit.** Export, print, or share only with the labels (purpose, owner,
    date, retention, classification) the programme requires.

For a new heat map or risk dashboard, also load the design and UI skills. This
skill supplies the risk semantics; those supply the visual system.

## New thinking worth using (and its limits)

| Idea | Use it for | Do not |
|---|---|---|
| Cyber risk quantification (FAIR/OpenFAIR) | Comparing controls and insurance in financial terms | Presenting a single point as audited fact |
| Scenario and stress testing | Severe but plausible events, resilience gaps | Treating a scenario as a probability |
| Reverse stress testing | Finding the failure that breaks the business | Stopping at the first uncomfortable answer |
| Operational resilience / impact tolerance | Maximum tolerable disruption, recovery capability | Confusing RTO with risk appetite |
| Dynamic risk and KRIs | Trigger-based re-assessment, leading indicators | A dashboard of metrics with no decision rule |
| Risk velocity and persistence | Time-to-impact and duration, not just likelihood | Averaging velocity into a score |
| Concentration and correlation | Shared vendors, platforms, identities, regions | Assuming independence because data is separate |
| Third- and nth-party risk | Supplier chains and inherited exposure | Treating a questionnaire as assurance |
| AI governance (NIST AI RMF, ISO/IEC 42001) | Model inventory, evals, human oversight, data provenance | Calling a policy an AI control |
| Climate (TCFD/ISSB) | Transition and physical risk, scenario disclosure | Borrowing another entity's materiality |
| Risk culture and behaviour | Incentives, speak-up, near-miss reporting | Measuring culture with one survey score |
| Assurance mapping (three lines) | Who assures what, with what evidence, at what cadence | Duplicating the same test in three functions |
| Continuous assurance | Evidence from systems, not annual screenshots | Calling automated coverage "compliance" |

The durable ideas are the ones that change a decision. Fashionable frameworks
that produce a new diagram and no new action are overhead.

## Standards map

Verify the current edition before quoting a clause; standards are revised and
some are licensed.

| Reference | Use for | Caution |
|---|---|---|
| ISO 31000:2018 | Risk principles and process | Guidance, not certifiable; not a scoring method |
| IEC 31010:2019 | Choosing risk assessment techniques | Techniques are tools, not a programme |
| COSO ERM 2017 | Enterprise risk, strategy, performance | Enterprise framing, not a control catalogue |
| NIST RMF (SP 800-37) | Security risk lifecycle | US federal orientation; adapt the language |
| NIST CSF 2.0 | Outcome-based cyber programme | Not a risk quantification method |
| ISO/IEC 27005:2022 | Information security risk | Align with ISO 27001 context |
| FAIR / OpenBAIR | Cyber loss taxonomy and quantification | Calibration and data quality decide the result |
| ISO 22301 | Business continuity management | Continuity is not the whole resilience picture |
| DORA (EU) | Digital operational resilience | Check applicability and current text |
| Basel operational risk / Solvency II ORSA | Regulated operational risk and capital | Actuarial and regulatory expertise required |
| IIA Three Lines Model | Assurance roles and independence | Does not replace management ownership |
| ISO/IEC 42001:2023, NIST AI RMF 1.0 | AI management and risk | Emerging; document assumptions |
| TCFD / ISSB IFRS S2 | Climate-related disclosure | Materiality and scenario choices are entity-specific |
| SOC 2, ISO 27001, PCI DSS, HIPAA | Compliance frameworks and control sets | Compliance evidence is not a risk assessment |

## Regulatory lens

- **Canada:** PIPEDA, Ontario PHIPA, Quebec Law 25, OSFI E-21 for federally
  regulated financial institutions, PCMLTFA for AML. Check applicability and
  current guidance; do not treat a framework as law.
- **United States:** SEC cyber disclosure, NYDFS Part 500, HIPAA, GLBA, state
  breach laws. Federal and state obligations can differ.
- **EU/UK:** GDPR, NIS2, DORA, FCA/PRA operational resilience. Extra-territorial
  reach is common; confirm with counsel.
- **Sector:** PCI DSS for card data, HIPAA for PHI, FedRAMP for cloud services,
  CMMC for defence suppliers.

Compliance mapping is a crosswalk with identifiers and provenance, not copied
standard text. Licensed standards stay upstream.

## Workflow when invoked

1. **Clarify the decision and audience.** Board, risk committee, control owner,
   auditor, or operator? What will they do differently?
2. **Establish scope and horizon.** Entity, process, system, vendor, portfolio;
   12 months, 3 years, or a single scenario.
3. **Inspect the evidence.** Register fields, scoring rules, loss data, incident
   history, control tests, assurance reports, prior decisions.
4. **Test the arithmetic and logic.** Recompute scores, check monotonicity,
   aggregation, base rates, tails, and correlation. Run the checklists above.
5. **Stress the judgement.** What would have to be true for this score to be
   wrong? What is the reference class? What is missing?
6. **Recommend changes with acceptance tests.** A specific change, the decision
   it improves, the evidence it needs, and how to tell whether it worked.
7. **State residual uncertainty.** What is observed, inferred, assumed, and still
   unknown.

## Output contract

Use this shape for a risk review or design note:

```
Observed:   what the register/map/data actually shows (counts, formulas, dates)
Inferred:   what you conclude, with confidence and the reasoning
Assumption: what must hold for the inference to be valid
Recommend:  the change, the decision it supports, and the acceptance test
Unknown:    what evidence would materially change the answer
```

Rules:

- Never invent a number. If a figure is illustrative, label it.
- Separate observed loss data from expert judgement.
- Show the formula and the units.
- Give ranges where the input is uncertain.
- Prefer a decision rule ("High requires programme-lead acceptance") over a
  colour.
- If the data cannot support the requested precision, say so and propose the
  smallest evidence collection that would change the decision.

## Anti-patterns

- Multiplying ordinal labels and reporting two decimal places.
- A heat map with no counts, no methodology version, and no drill-down.
- "Residual risk" that falls without a named control or test.
- Risk appetite expressed only as a threshold, with no authority or expiry.
- Register growth as a success metric.
- One risk owner for everything because "the CISO owns risk".
- Counting a control as effective because an audit passed once.
- Summing worst cases, percentiles, or scores across a portfolio.
- Treating a vendor questionnaire as independent assurance.
- Using compliance coverage as a proxy for risk reduction.
- AI or climate risk added to the register as a single line with no scenario.
- A board report that lists forty risks and recommends nothing.

## OpenGRC specifics

- The risk register is `Risk` with inherent and residual 1–5 likelihood and
  impact; the score is `likelihood x impact`. See
  `docs/grc/risk-methodology.md` and `app/Enums/RiskLevel.php`.
- The global appetite threshold is `risk.appetite_threshold`, displayed on the
  **Risk heat map** page. It is a display threshold; the real decision is the
  acceptance record (acceptor, rationale, expiry) on the risk.
- `opengrc:risks:acceptance` expires acceptances. Re-acceptance is a new decision,
  not a date edit.
- Mitigations are per action; link them to the risk and name the implementation
  that delivers the control.
- Findings link to risks and remediation tasks. A repeat finding on the same
  control is evidence that the treatment or the control test is inadequate.
- The assurance matrix and gap report show evidence coverage, not compliance.
  State the difference in every report.
- Product changes follow `AGENTS.md`: append-only migrations, tests, an ADR for a
  decision with trade-offs, and no permission strings beyond the three roles.

## Boundaries

- Not legal advice. Regulatory applicability needs counsel or a qualified
  compliance professional in the jurisdiction.
- Not actuarial certification. Do not sign off reserves, capital, insurance
  pricing, or solvency positions from this skill.
- Not a substitute for independent assurance. The person who owns the control
  should not be the only person who attests to its effectiveness.
- Do not place personal data, credentials, or client-confidential content into a
  risk description or an AI prompt. Use a reference and a classification.
- Do not copy licensed standard text into registers, reports, or training.
