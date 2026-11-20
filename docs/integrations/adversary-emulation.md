# Adversary emulation evidence

Adversary emulation is how a programme tests detection and response against
real behaviour. Apache Caldera and similar engines run ATT&CK techniques against
lab hosts, with agents, payloads and command-and-control channels. That runtime
belongs in OpenCyberRange, not in the compliance console.

OpenGRC accepts a **completed-operation summary** as
`evidenceType: adversary-emulation`. The summary proves what was tested and
whether the sensors detected or the controls prevented it. It never contains
commands, payloads, credentials, agent tokens, raw logs or target hostnames.

See ADR 0022 for the boundary and ADR 0002 for the training split.

## Boundary

| OpenCyberRange / Caldera owns | OpenGRC owns |
|---|---|
| Agents, payloads, planners, target networks, operation state | The control, the evidence record and the audit trail |
| Command execution and raw operation output | Aggregate technique counts and coverage |
| Range authorization and stop conditions | The control link, validation rule and exception |
| Raw artifact retention | The summary, its hash and its age |

OpenGRC has no Caldera credentials, no operation control and no write-back. A
compromised GRC session cannot launch an attack.

## Request

```http
POST /api/v1/evidence
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json

{
  "id": "emulation:acme:op-123",
  "sha256": "<sha256 of the evidence object>",
  "evidenceType": "adversary-emulation",
  "capturedAt": "2026-11-20T10:00:00Z",
  "controlIdentifier": "CA-8",
  "evidence": {
    "schema": "opengrc.adversary-emulation/v1",
    "range": "ocr-lab-1",
    "operationId": "op-123",
    "adversary": "APT29",
    "planner": "atomic",
    "library": "atomic-red-team",
    "libraryVersion": "2026.11",
    "startedAt": "2026-11-20T08:00:00Z",
    "finishedAt": "2026-11-20T09:15:00Z",
    "techniquesAttempted": 12,
    "techniquesSucceeded": 5,
    "techniquesDetected": 9,
    "techniquesPrevented": 7,
    "undetectedTechniques": 3,
    "unpreventedTechniques": 5,
    "testsExecuted": 14,
    "testsSucceeded": 9,
    "testsFailed": 5,
    "testIds": ["T1059.001-1", "T1053.005-2"],
    "detectionCoveragePercent": 75.0,
    "preventionCoveragePercent": 58.3,
    "assetIds": ["asset-42", "asset-77"],
    "notes": null
  }
}
```

`sha256` is the canonical JSON of `evidence`: recursively key-sorted, compact
separators, UTF-8, unescaped slashes. It is the same hash rule as the other
external evidence types.

| Field | Required | Meaning |
|---|---|---|
| `range` | yes | The isolated lab or range that ran the operation |
| `operationId` | yes | The engine's operation id; the reproducibility key |
| `adversary` | no | Adversary profile name, not a person |
| `planner` | no | Planner that chose the technique order |
| `library` | no | Test library: `atomic-red-team`, `caldera`, `custom` |
| `libraryVersion` | no | Library release, for reproducibility |
| `startedAt`, `finishedAt` | no | Operation window in UTC |
| `techniquesAttempted` | yes | Distinct ATT&CK techniques attempted |
| `techniquesSucceeded` | yes | Techniques that executed successfully |
| `techniquesDetected` | yes | Techniques with a corresponding alert |
| `techniquesPrevented` | yes | Techniques blocked before execution |
| `undetectedTechniques` | yes | Succeeded minus detected; producer-computed |
| `unpreventedTechniques` | yes | Succeeded minus prevented; producer-computed |
| `testsExecuted` | no | Atomic tests run, when the library reports tests rather than techniques |
| `testsSucceeded` | no | Atomic tests that executed successfully |
| `testsFailed` | no | Atomic tests that failed to execute |
| `testIds` | no | Test identifiers such as `T1059.001-1`; never the test body |
| `detectionCoveragePercent` | no | Producer-computed, for simple rules |
| `preventionCoveragePercent` | no | Producer-computed, for simple rules |
| `assetIds` | no | OpenGRC asset ids or range-neutral identifiers |
| `notes` | no | Short operational note; never command output |

Producer-computed counts are deliberate: the validation-rule language compares a
path to a constant, not two paths to each other. Compute the gap in the range
where the raw facts are available.

## Atomic Red Team is a library, not a service

Atomic Red Team is a library of tests mapped to ATT&CK techniques, executed by
`Invoke-AtomicRedTeam`, by the Atomic Red Team executor, or through Caldera's
Atomic plugin. It has no service to connect to. The range runs the tests and
reports `library: atomic-red-team`, the library version, test IDs and counts;
OpenGRC never stores the test body or the command.

If the range measures techniques rather than individual tests, leave the test
fields out. Both granularities are valid as long as the summary says which one it
used and does not mix them.

## Status and validation

The record is stored as `unknown`. A validation rule decides the control. For a
detection objective:

```json
{
  "match": { "evidence_type": "adversary-emulation" },
  "assert": [
    { "path": "techniquesAttempted", "op": "gte", "value": 1 },
    { "path": "undetectedTechniques", "op": "eq", "value": 0 }
  ]
}
```

For an Atomic Red Team run, `testsFailed` is usually the execution-health check:
a test that cannot run says nothing about detection. Add
`{ "path": "testsFailed", "op": "eq", "value": 0 }` when the summary reports tests.

For a prevention objective, use `unpreventedTechniques`. A softer programme can
assert a percentage threshold instead. A missing value is Unknown, never a pass.

Typical control links:

| Control family | What the evidence supports |
|---|---|
| NIST 800-53 CA-8, SI-4, IR-4 | Control testing, monitoring and incident handling |
| ISO 27001 A.8.8 | Technical vulnerability and control testing |
| SOC 2 CC4.1, CC7.1 | Monitoring and evaluation of controls |

## Safety and authorization

- Run only in an isolated range with written authorization, an approved target
  list and a stop condition.
- Do not target production, personal devices or out-of-scope systems.
- Use asset IDs or range-neutral identifiers, not employee names, hostnames or
  IP addresses that identify a person.
- No command output, payload, credential, agent token, file content or raw log
  line in the summary.
- A failed technique is a result, not a reason to rerun until it passes. Record
  the exception and the remediation.

## Replay and failure handling

Deduplication is per `(client, external id)`. A new operation needs a new `id`.
`201` creates, `200` replays the original, `422` is a hash or payload error,
`401`/`403` are credential errors.

If delivery fails, the previous summary stays visible and ages. A missing summary
is not a clean control.

## Limits

- No operation control, orchestration or write-back from OpenGRC.
- No raw operation data, artifacts or command output.
- No real-time view; the range owns the live dashboard.
- No claim that a lab result proves production effectiveness.
- No automatic mapping from a technique to a control; the control link is a
  deliberate decision.
