# Regulatory change register

A regulation or standard changes. The register records what changed, whether it
applies, which frameworks it touches and what the programme decided to do.

See ADR 0039. This is a human-maintained register, not a feed and not a copy of
the regulator's text.

## What belongs here

- A new or amended regulation, standard, certification scheme or contractual
  security requirement.
- A change that could alter controls, policies, evidence, contracts or risk.
- A change the programme has decided to monitor but not yet assess.

Do not use it for general security news or vendor advisories. Use the guidance
register for those.

## Fields

| Field | Why it exists |
|---|---|
| Title | A short statement of what changed |
| Regulator or publisher | Who issued it |
| Source link | Where to read the original |
| Published / effective | When it was issued and when it applies |
| Status | New, assessing, implemented, no impact, closed |
| Impact | Low, medium, high or critical judgement |
| Summary | The change in your own words |
| Impact assessment | What it means for controls, policies, evidence and contracts |
| Owner | Who is accountable for the assessment |
| Assessment due | When the decision is expected |
| Assessed | When the impact assessment was completed |
| Affected frameworks | Imported standards or control libraries it touches |
| Notes | Internal context |

## Lifecycle

| Status | Meaning |
|---|---|
| New | Recorded, not yet being assessed |
| Assessing | An owner is working through the impact |
| Implemented | The response is in place and recorded |
| No impact | Assessed and found not applicable, with a reason |
| Closed | No further action; keep the record |

An open change past its assessment due date is shown in red and can be filtered as
overdue. Nothing sends a reminder.

## How to assess a change

1. Read the source. Summarise it; do not paste it.
2. Decide whether it applies to the organisation, and write the reason.
3. Link the affected frameworks.
4. Identify the controls, policies, risks and evidence that need a change.
5. Record the impact and the owner.
6. Set an assessment due date.
7. When the response is in place, mark implemented and record the evidence.
8. If it does not apply, mark no impact and keep the rationale.

## Measurement

The register supports a simple programme measure:

- open changes by status;
- overdue assessments;
- changes by impact;
- time from publication to assessment.

It does not compute a compliance score and does not diff revisions.

## Limits

- No regulator feed, scraper or subscription.
- No automatic applicability decision.
- No revision diff or "what changed" extraction.
- No notification or escalation.
- Affected frameworks are linked; control, risk and evidence links are manual.
- No regulatory text mirror; the source link is the reference.
