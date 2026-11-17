# OpenGRC × OpenCyberRange: the GRC training integration

Status: design. Nothing here is implemented yet. The boundary rule is already
accepted in [`docs/decisions/0002-training-integration-boundary.md`](../decisions/0002-training-integration-boundary.md).

## The idea

Most GRC training teaches vocabulary. Learners memorise control families and then
never see a control actually operate. Most cyber range training teaches technique
and never asks who owns the resulting evidence, which audit it satisfies, or what
happens when the finding is disputed.

Put them together and the exercise becomes the job:

1. Do the technical work in the range (find it, fix it, log it).
2. Record the governance consequence in OpenGRC (map it, evidence it, risk it).
3. Defend it in an audit workflow (scope it, test it, remediate it).

The range provides ground truth. OpenGRC provides the accountability trail. Neither
is sufficient alone.

## Who it serves

| Persona | Wants | Uses |
|---|---|---|
| Learner | A portfolio of defensible work | Range labs + OpenGRC records they can show |
| Instructor | Reproducible grading | Scenario evidence contracts + rubrics |
| Programme lead | Evidence the training works | Cohort metrics from audit outcomes |
| Auditor (real or simulated) | Traceability | Standard -> control -> implementation -> evidence |

## Learning arc

A coherent progression, not a list of labs:

```
Foundation   read a framework, map one control, write an implementation
Operations   produce evidence for a control from real technical output
Detection    turn a lab finding into a risk with a treatment decision
Audit        scope an audit, test the control, raise and remediate a finding
Programme    run a quarterly review with residual risk and exceptions
```

Each stage has an artefact. The learner's file grows; by the end it is a miniature
GRC programme with evidence, not a certificate of attendance.

## Range tracks to GRC themes

Indicative mapping. The rationale is what matters; the identifiers will vary by the
framework the cohort is using.

| Range track | Technical work | GRC theme | Example controls |
|---|---|---|---|
| Identity / IAM | Access review, privilege escalation | Access control, least privilege | 800-53 AC-2, AC-6; SOC 2 CC6.1-CC6.3 |
| Log analysis / detection | Review logs, triage an event | Audit logging, monitoring | 800-53 AU-6, AU-12; SOC 2 CC7.2 |
| Firewalls / network | Rule review, segmentation test | Boundary protection | 800-53 SC-7; CIS 4, 12 |
| Vulnerabilities / web | Find and validate a weakness | Vulnerability management, remediation | 800-53 RA-5, SI-2; ISO A.8.8 |
| Backups / resilience | Restore test, RTO exercise | Availability, backup | 800-53 CP-9, CP-10; ISO A.8.13 |
| Malware / forensics | Analyse, contain, document | Incident response | 800-53 IR-4, IR-6; ISO A.5.24-25 |
| Vendor scenarios | Assess a third party | TPRM | 800-53 SR-3; SOC 2 CC9.2 |
| Policy / awareness | Write and acknowledge policy | Governance, training | 800-53 PL-1, AT-2; ISO A.5.1 |

The mapping is per lab and lives with the lab metadata, with a written rationale.
See `.opencode/skills/opengrc-opencyberrange-integration/SKILL.md` for the shape.

## What gets built

### Phase 0 — manual bridge (no code)

- Two labs, authored with explicit control mappings and evidence requirements.
- Learners create records in OpenGRC by hand from range output.
- Purpose: prove the exercise is teachable and the evidence is gradeable before
  writing an integration.

Exit criteria: two learners complete both labs; an instructor grades them from
artefacts alone; the rubric needs no verbal clarification.

### Phase 1 — evidence ingestion (one direction)

- Range emits `lab.completed` with artifacts.
- OpenGRC consumes it on a queue, verifies checksums, stores artifacts, and creates
  evidence rows linked to the declared controls.
- Unmapped or unknown controls are parked, never guessed.

Exit criteria: replay-safe ingest, golden-file tests, dead-letter path, an operator
runbook, and no synchronous dependency between the two systems.

### Phase 2 — scenario packaging

- A scenario becomes a first-class object: lab refs + required GRC state +
  acceptance checks.
- Instructor can launch a cohort, and see per-learner completion from records.
- Grading reads OpenGRC state, not the range.

Exit criteria: a cohort of ten runs end to end without instructor data entry.

### Phase 3 — audit simulation

- A simulated auditor account with scoped access.
- Audit items generated from scenario acceptance criteria.
- Findings become remediation tasks with owners and due dates.

Exit criteria: learners defend evidence in an audit conversation; findings are
raised and closed in the system.

## Data the integration needs (future work)

Sketch only; not migrated yet.

| Concept | Where | Purpose |
|---|---|---|
| `external_identity` | OpenGRC | Map range user to OpenGRC user without a second auth realm |
| `evidence.source`, `source_id`, `payload_version` | OpenGRC | Provenance and idempotency on every ingested artifact |
| `lab_reference` | Range metadata, mirrored | Lab id + version, so an evidence record stays interpretable |
| `control_ref` on scenario | Both | The join that makes the exercise a GRC exercise |
| `ingest_event` log | OpenGRC | Replay, audit, and failure triage |

## Risks and mitigations

| Risk | Why it matters | Mitigation |
|---|---|---|
| Cargo-cult compliance | Learners click the right fields with no reasoning | Evidence contract + debrief questions |
| Coupling uptime | Range outage blocks grading, or vice versa | Queued, idempotent, degrade gracefully |
| Data privacy | Learner activity crosses systems | Minimal identity mapping, no bulk export, retention policy |
| Content drift | Lab changes but mapping does not | Lab version in the payload; mapping review on lab release |
| Grading automation bias | A script decides competence | Automate state checks; humans judge quality |
| Two upgrade tails | Both platforms need maintenance | Documented contract + compatibility test |

## Measures of success

- Share of learners whose artefacts pass acceptance without rework.
- Time from lab completion to defensible evidence.
- Number of audit items answerable from stored evidence in under five minutes.
- Instructor time per learner, trending down.
- Cohort retention into the next stage of the arc.

Deliberately not a success measure: number of labs completed, or number of controls
"covered". Coverage without evidence is the failure mode this integration exists to
fix.

## Open questions

1. Which framework is the default for cohorts? One primary framework is easier to
   teach than three.
2. Do learners get their own OpenGRC organisation, or a shared one with scoping?
   Shared is cheaper; separate teaches isolation.
3. How long is learner evidence retained, and who can delete it?
4. Does the range emit artifacts, or must OpenGRC pull them before they expire?
   Push with checksums is simpler and survives pruning.
5. Is the simulated auditor a human role or an AI assistant with scoped tools? The
   MCP layer makes the second option cheap, but grading must stay explainable.
