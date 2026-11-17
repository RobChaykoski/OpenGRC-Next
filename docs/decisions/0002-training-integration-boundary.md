# 0002 — Boundary between OpenGRC and OpenCyberRange

Status: accepted
Date: 2026-11-17

## Context

OpenGRC is a governance, risk and compliance system of record. OpenCyberRange is a
teaching environment: courses, labs, range hosts, and learner progress. The
long-term goal is to train people to *do* GRC with their hands, not to read about
it, which means the two systems have to cooperate.

The failure mode to avoid is merging them. A GRC platform that also schedules
range containers inherits the upgrade, security, and operational surface of both.
A range platform that stores compliance records becomes a system of record nobody
audits.

## Decision

Keep two systems with one explicit contract.

**OpenGRC owns:**

- standards, controls, implementations, evidence, risks, audits, findings,
  vendors, policies, and approvals
- the audit trail and retention of compliance artifacts
- who is accountable for a control

**OpenCyberRange owns:**

- courses, modules, labs, range hosts, learner sessions, and technical scoring
- lab content and lab runtime lifecycle
- technical evidence *capture* (command output, screenshots, logs)

**The contract is a small, versioned event/evidence payload** documented in
`docs/integrations/opencyberrange-grc-training.md`:

- A lab declares which control(s) it exercises and which artifacts it produces.
- On lab completion the range emits a `lab.completed` event with the learner,
  course, lab reference, control refs, and evidence blobs or links.
- OpenGRC stores that as evidence linked to an implementation, and an audit
  workflow can then evaluate it.

Rules:

1. OpenGRC never calls the range API synchronously on a user request. Ingestion is
   queued and idempotent.
2. The range never writes GRC records directly. It emits events; OpenGRC decides
   what they mean.
3. Lab content is not duplicated into OpenGRC. Store references and outcomes.
4. Every integration record carries the source system, source id, and payload
   version so a later schema change is a migration, not an archaeology project.
5. Integration failures degrade gracefully: a missing lab event must not block GRC
   work, and a GRC outage must not block a lab from running.

## Consequences

- Two deployments, two upgrade cadences, one documented seam. More operational
  work than a monolith, far less than a merged product.
- The contract needs versioning and a compatibility test from day one.
- Training scenarios become reviewer-friendly: technical work and compliance
  evidence are separate artifacts that reference each other.
- Deliberate non-goal: real-time bidirectional sync. Batch/queued ingestion is
  enough for training and much easier to keep correct.

## Alternatives considered

- **Single combined application.** Rejected: conflates two security domains and
  two release schedules, and makes the GRC product depend on container runtime
  availability.
- **Shared database.** Rejected: two schemas writing one schema has no ownership
  and no clean upgrade path.
- **Manual copy-paste between systems.** Rejected: no traceability, which is the
  one thing a GRC tool exists to provide.
