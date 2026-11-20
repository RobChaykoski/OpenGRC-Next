# 0034 — Exercises are a register, not a training transcript

Status: accepted
Date: 2026-11-20

## Context

Tabletop exercises, simulations and red-team engagements are how an organisation
tests its plans, roles and decisions. CISA's Tabletop Exercise Packages (CTEPs)
provide scenarios and facilitator material; a vendor or the internal team may run
the exercise.

An exercise is not a training completion record. Training says a person learned
something; an exercise says the organisation tested a plan and found gaps. Both
matter, and conflating them loses the findings and corrective actions.

## Decision

1. **One record per exercise.** The register captures the type, source, scenario
   summary, objectives, scope, schedule, facilitator, participant groups, findings
   and corrective actions.
2. **The package stays upstream.** A CTEP or vendor package is referenced by name
   or id. Do not copy the facilitator guide or scenario material into the
   repository.
3. **An exercise is not training.** A completed exercise is evidence that a plan
   was tested. A training completion is a separate record. Do not mark a person
   trained because they attended a tabletop.
4. **Findings and actions are the point.** A completed exercise with no findings
   summary or corrective actions is incomplete. Link a corrective action to a
   finding or a risk where one exists.
5. **Participant groups, not personal profiles.** Record teams and roles. Do not
   store attendee contact details or performance notes in the exercise register.
6. **A completed exercise is immutable evidence.** Re-test with a new exercise;
   do not rewrite the old one to make the result look better.
7. **Evidence is a reference and a hash.** The attendance sheet, notes, slides or
   after-action report stay in the document store; the register holds the pointer.
8. **No automatic scheduling or reminder.** The register shows planned and
   completed exercises; the programme schedules them.

## Consequences

- The programme can show that it tested the incident-response plan, not only that
  people read it.
- The register is only as good as the after-action record. An exercise with no
  findings is either perfect or unexamined; the register makes that visible.
- A CTEP is a starting point, not a compliance certificate. The organisation's
  objectives and findings are the evidence.
- Corrective actions can outlive the exercise. Link them to the finding/risk
  register so they are tracked to closure.
- Participant groups avoid a new personal-data pool while still showing who was
  involved.

## Alternatives considered

- **Keep CTEP PDFs in a shared drive.** Rejected: no objectives, findings,
  actions, facilitator or evidence link.
- **Use training completion records for exercises.** Rejected: attendance is not a
  test of the plan, and the findings disappear.
- **Store the full scenario package.** Rejected: licensing, maintenance and a
  duplicate of the upstream material.
- **No register until an audit asks.** Rejected: the after-action findings and
  actions are exactly what the audit asks for.
- **Record individual performance.** Rejected: the exercise tests the plan and the
  team, not a person's score.
