---
name: OpenGRC Training Scenario Authoring
description: Use when writing hands-on GRC exercises, labs, or assessments that use OpenGRC as the system of record. Defines the anatomy of a scenario, the evidence contract, the rubric, and how to keep exercises realistic and gradeable.
---

# OpenGRC Training Scenario Authoring

The goal is to teach people to *operate* a GRC programme, not to click through it.
A scenario is a small, realistic piece of an organisation with a decision to make
and evidence to produce.

## The rule

**Every scenario ends with an artefact a stranger could audit.** If the learner
finishes by "exploring the risk register", the exercise is a tour, not training.

## Anatomy of a scenario

| Part | Content | Why it exists |
|---|---|---|
| Situation | A short, concrete organisational context | Gives decisions consequences |
| Role | Who the learner is (analyst, control owner, auditor) | Different roles see different authority |
| Objective | One sentence, measurable | Keeps scope honest |
| Inputs | Records that already exist in OpenGRC, and lab output from the range | Forces use of real data |
| Tasks | Ordered steps that change state in the system | The hands-on part |
| Evidence | Exactly what must exist at the end | The gradeable part |
| Acceptance criteria | Observable checks, not opinions | Makes grading reproducible |
| Debrief | What a real programme would do next | Turns clicks into judgement |

## Design sequence

1. **Pick one control and one failure mode.** "Vendor onboarding without a
   security questionnaire" beats "vendor risk management".
2. **Write the acceptance criteria first.** If you cannot state them as checks on
   records, the scenario is not ready.
3. **Work backwards to tasks.** Each task should produce one piece of the evidence.
4. **Seed only what the learner cannot reasonably create.** Standards and control
   libraries exist already; do not make them retype a framework.
5. **Add one ambiguity.** A missing owner, a stale review, an out-of-scope asset.
   Handling ambiguity is the transferable skill.
6. **Test it on yourself with a stopwatch.** A 90-minute scenario that takes four
   hours is a project, not a lab.

## Evidence contract

Each scenario declares its evidence up front, in a form both a human and an
integration can evaluate:

```yaml
scenario: vendor-onboarding-gap
control_refs:
  - framework: "SOC 2"
    id: "CC9.2"
evidence_required:
  - type: implementation
    match: { control_ref: "CC9.2", status: implemented }
  - type: file_attachment
    match: { entity: vendor, filename_pattern: "*.pdf" }
  - type: risk
    match: { treatment: mitigate, owner: not_null }
  - type: lab_artifact
    source: opencyberrange
    match: { lab: "CH_VENDOR_01", artifact: "scan_report" }
acceptance:
  - "Implementation owner is set"
  - "Evidence is attached to the implementation, not the vendor only"
  - "Residual risk is recorded with a treatment decision"
```

## Rubric

Grade on observable state, and make the rubric visible to the learner.

| Level | Looks like |
|---|---|
| Not yet | Records exist but nothing links: evidence unattached, no owner |
| Working | The chain standard -> control -> implementation -> evidence is complete |
| Competent | Also handles scope, exception, or not-applicable correctly, with a reason |
| Strong | Adds a defensible judgement: residual risk, review cadence, or a test plan |

Never grade on prose quality alone. A well-written implementation with no evidence
is a fail, and saying so is part of the lesson.

## Anti-patterns

- **Click-path labs.** "Navigate to X and press Y" teaches the UI, not the job.
- **Pre-filled answers.** Sample content that already contains the conclusion.
- **Unverifiable deliverables.** "Write a good policy" cannot be graded.
- **Toy data that hides the hard part.** Real programmes have duplicates, stale
  reviews, and missing owners. Include them.
- **Scenarios that require a personal opinion about a real employer.** Keep it
  fictional or abstract.
- **Hidden requirements.** If it is graded, it is stated.

## Debrief prompts

End every scenario with questions that transfer:

1. What would have caught this earlier in a real programme?
2. Most of the effort was in which step, and is that where a real team spends it?
3. Which piece of evidence would an auditor ask for first, and could you produce it
   in five minutes?
4. What would you automate, and what must stay a human judgement?
