# Exercises and tabletops

An exercise tests a plan, a role or a decision under realistic pressure. A CISA
CTEP, a vendor scenario or an internal tabletop is the starting point; the
register records the objectives, participants, findings and corrective actions.

See ADR 0034 for the boundary. An exercise is not a training completion.

## What belongs here

| Type | Example |
|---|---|
| Tabletop | A ransomware scenario discussed by the incident-response team |
| Simulation | A technical simulation of a failure or attack |
| Functional | A live test of a recovery or failover function |
| Red team | An adversary-emulation engagement with a report |
| Other | A vendor or sector exercise |

The **source** records `CISA CTEP`, `Internal`, `Vendor` or `Other`. The source
reference is a package name or id; do not copy the package. An external
curriculum is `Other`; `docs/integrations/digital-forensics-lab.md` is one we
reference, with the licence limits on what may not be copied.

## Fields

| Field | Why it exists |
|---|---|
| Name | What was run |
| Type | Tabletop, simulation, functional or red team |
| Source and reference | Where the scenario came from |
| Scenario summary | A short description, not the full package |
| Objectives | What the exercise set out to test |
| Scope | Systems, teams and roles in scope |
| Scheduled / conducted | When it was planned and when it ran |
| Status | Planned, completed or cancelled |
| Facilitator | Who ran it |
| Participant groups | Teams and roles, not personal profiles |
| Findings summary | What did not work |
| Corrective actions | What will change, and by whom |
| Evidence reference and hash | The after-action report, notes or attendance record |

## Lifecycle

| Status | Meaning |
|---|---|
| Planned | Scheduled, not yet run |
| Completed | Run, with findings and actions recorded |
| Cancelled | Did not run; record why |

A completed exercise with no findings summary or corrective actions is
incomplete. A finding of "no gaps" is acceptable, but it should be explicit.

## After-action review

1. Capture the objectives and whether they were met.
2. Record the findings without attributing them to a person.
3. Turn each finding into a corrective action with an owner and a date.
4. Link the action to a finding or a risk where one exists.
5. Store the after-action report and the attendance evidence; record the
   reference and hash.
6. Re-test with a new exercise after the actions close.

## Cadence

| Frequency | Activity |
|---|---|
| Annually | At least one incident-response tabletop |
| Quarterly | A focused exercise for a high-risk service or scenario |
| On major change | A tabletop for a new critical service, vendor or region |
| After an incident | A review exercise to test the corrective actions |

## Evidence

The register is evidence that the exercise was planned, run and reviewed. The
after-action report is the detail. For an audit, keep:

- the exercise record and objectives;
- participant groups and facilitator;
- findings and corrective actions;
- the after-action report reference and hash;
- links to findings, risks and control tests;
- the next exercise date.

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| Exercise with no findings | The review was shallow | Require a findings summary and actions |
| Attendance counted as training | Training and exercise conflated | Keep training records separate |
| Actions never close | No owner or date | Link each action to a finding/risk with a due date |
| Scenario copied into the repo | Licensing and drift | Reference the package; keep a summary |
| Same scenario every year | No learning | Change the scenario or the objectives |
| Exercise cancelled silently | No record | Mark cancelled with a reason |

## Limits

- No built-in scenario library; use CTEPs or your own material.
- No scheduling or reminder engine; the register shows planned exercises.
- No individual performance scoring; the exercise tests the plan and team.
- No automatic finding creation; link the corrective action manually.
- No package mirroring; the source and reference are the link.
