---
name: OpenGRC OpenCyberRange Integration
description: Use when connecting OpenGRC with OpenCyberRange - mapping labs to controls, ingesting lab evidence, designing the event contract, or building the training integration. Covers the boundary, payload shape, idempotency, and failure behaviour.
---

# OpenGRC OpenCyberRange Integration

Two systems, one contract. Read `docs/decisions/0002-training-integration-boundary.md`
first; it is the authority if this skill and the ADR disagree.

## The boundary in one line

**OpenCyberRange runs the lab; OpenGRC records the governance outcome.**

- Range owns: courses, modules, labs, hosts, sessions, technical scoring, raw
  artifact capture.
- OpenGRC owns: standards, controls, implementations, evidence, risks, audits,
  findings, policies, approvals.
- Neither writes directly into the other's database.

## Mapping labs to controls

A lab declares its GRC relevance explicitly. Do not infer it from keywords.

```yaml
# range-side lab metadata
lab: CH_LIN05_Detection
track: linux
level: intermediate
grc:
  controls:
    - framework: "NIST SP 800-53"
      id: "AU-6"
      rationale: "Learner reviews and acts on audit log anomalies"
    - framework: "SOC 2"
      id: "CC7.2"
      rationale: "Detection of security events"
  evidence:
    - artifact: detection_report
      type: document
      description: "Analyst write-up of the detected event and response"
    - artifact: log_excerpt
      type: file
      description: "Relevant log lines with timestamps"
```

Rules:

- Mapping is per lab, recorded once, reviewed like any other content.
- `rationale` is required. A bare identifier is a guess with formatting.
- One lab can map to several controls, but keep it honest: two or three is normal,
  ten means the lab is unfocused.
- Do not map a lab to a control it only mentions in passing.

## Event contract

The range emits events; OpenGRC consumes them asynchronously.

```json
{
  "schema_version": "1.0",
  "event": "lab.completed",
  "event_id": "01J...",
  "occurred_at": "2026-11-17T12:00:00Z",
  "source": "opencyberrange",
  "actor": {
    "external_id": "student-4821",
    "email_hash": "sha256:..."
  },
  "course": { "code": "GRC-201", "term": "2026-Fall" },
  "lab": { "ref": "CH_LIN05_Detection", "version": "1.3.0" },
  "control_refs": [
    { "framework": "NIST SP 800-53", "id": "AU-6" }
  ],
  "artifacts": [
    {
      "name": "detection_report",
      "mime": "application/pdf",
      "checksum": "sha256:...",
      "url": "https://range.example/artifacts/...",
      "expires_at": "2026-12-17T12:00:00Z"
    }
  ]
}
```

Consumption rules:

1. **Idempotent by `event_id`.** Replays are normal; duplicates are not.
2. **Queued, never synchronous with a user request.**
3. **Checksum on ingest.** If the artifact hash does not match, reject and record why.
4. **Store `schema_version`, `source`, and the source id** on every ingested record.
5. **Never overwrite GRC judgement.** Lab data creates evidence; a human or an audit
   decides what it means.
6. **Expiring artifact URLs are copied into OpenGRC storage at ingest**, or the
   evidence disappears when the range prunes it.

## Identity

Do not invent a second identity system. Map range users to OpenGRC users through an
explicit link table (`external_id`, `source`, `user_id`). If a learner has no
OpenGRC account, hold the event in a pending state; do not auto-create privileged
accounts from an external system.

## Failure behaviour

| Failure | Behaviour |
|---|---|
| Range unreachable | OpenGRC unaffected; events queue upstream or retry later |
| OpenGRC unreachable | Lab completion still succeeds; range retries with backoff |
| Unknown control ref | Accept the event, flag the evidence as unmapped, alert |
| Unknown learner | Park the event; do not guess a match |
| Artifact expired | Mark evidence incomplete; never silently store a dead link |
| Schema version unknown | Reject to a dead-letter queue; do not half-parse |

## Testing the contract

- Golden-file tests for each `schema_version`.
- Replay test: send the same event three times, assert one record.
- Negative tests: wrong checksum, unknown control, expired URL.
- A compatibility test that fails when the producer adds a required field without a
  version bump.

## What not to build

- A synchronous "complete lab -> create GRC records" path. It couples uptime.
- Bidirectional sync of every field. Decide ownership per field, and stop there.
- A shared database or a shared auth realm for convenience.
- Automatic grading of compliance judgement. Ingest facts; humans conclude.
