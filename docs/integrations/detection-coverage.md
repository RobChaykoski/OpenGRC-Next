# Detection coverage evidence (YARA and Sigma)

OpenGRC does not run YARA or Sigma. Detection platforms do. OpenGRC accepts a
hash-verified **detection-coverage** snapshot so a monitoring control has
evidence: how many rules are deployed, how many fail validation, which ATT&CK
techniques are covered, and when the platform last proved it.

See ADR 0021 for the boundary. Rule authoring and execution belong to
OpenDetectionEngine, the SIEM or the endpoint engine; OpenGRC owns the control,
the evidence and the exception.

## Who sends it

- **OpenDetectionEngine** when it owns the detection catalogue.
- **A SIEM** (Elastic, Splunk, Sentinel) that can report Sigma rule state.
- **An endpoint or sandbox** that can report YARA rule state and validation.
- **OpenCyberRange** after a lab exercise that validates rules against a scenario.

One producer per snapshot is normal. Do not merge two platforms into one payload
unless the `platform` field names the combined source and the counts are
unambiguous.

## Request

The endpoint is the same hash-verified external evidence API used by discovery:

```http
POST /api/v1/evidence
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json

{
  "id": "detection:acme:2026-11-20T10:00:00Z",
  "sha256": "<sha256 of the evidence object>",
  "evidenceType": "detection-coverage",
  "capturedAt": "2026-11-20T10:00:00Z",
  "controlIdentifier": "SI-4",
  "evidence": {
    "schema": "opengrc.detection-coverage/v1",
    "platform": "opendetectionengine",
    "sigmaRules": 42,
    "yaraRules": 18,
    "failingRules": 1,
    "attackTechniquesCovered": 37,
    "targets": ["elastic", "wazuh"],
    "lastValidatedAt": "2026-11-19T18:00:00Z",
    "lastValidatedAgeDays": 0,
    "notes": null
  }
}
```

`sha256` is computed over the canonical JSON of `evidence`: recursively
key-sorted, compact separators, UTF-8, unescaped slashes. This is the same rule
as discovery evidence; the test suite has a reference implementation.

What the fields mean:

| Field | Required | Meaning |
|---|---|---|
| `platform` | yes | The product that produced the snapshot |
| `sigmaRules` | yes | Sigma rules active on the target backends |
| `yaraRules` | yes | YARA rules active on the engines |
| `failingRules` | yes | Rules that failed their last validation |
| `attackTechniquesCovered` | no | Distinct ATT&CK techniques covered by active rules |
| `targets` | no | Backends the rules were validated on |
| `lastValidatedAt` | no | When the platform last proved the rule set |
| `lastValidatedAgeDays` | no | Producer-computed age in days, for simple rules |
| `notes` | no | Short operational note; never a rule body or a log line |

Do **not** send rule text, YARA strings, malware samples, log lines, hostnames
from an investigation, or credentials. Send counts, identifiers and hashes.

## Status is not set by the producer

The record is stored as `unknown`. A validation rule decides the control state,
so the same snapshot can be scored differently by framework or by control. This
prevents a producer from marking a control effective simply by posting a payload.

Example rule for a monitoring control:

```json
{
  "match": { "evidence_type": "detection-coverage" },
  "assert": [
    { "path": "sigmaRules", "op": "gte", "value": 25 },
    { "path": "yaraRules", "op": "gte", "value": 10 },
    { "path": "failingRules", "op": "eq", "value": 0 },
    { "path": "lastValidatedAgeDays", "op": "lte", "value": 7 }
  ]
}
```

A failing assertion makes the control Failed. A missing value is Unknown, never a
pass. Attach the rule to the control that owns monitoring, then map that control
to the frameworks you report.

Typical control links:

| Control family | What the evidence supports |
|---|---|
| NIST 800-53 SI-4, AU-6, IR-4 | Monitoring, audit review and incident handling |
| ISO 27001 A.8.16 | Monitoring activities |
| SOC 2 CC7.2, CC7.3 | Detection and evaluation of security events |

## Responses and replay

| Status | Meaning |
|---|---|
| `201` | Created |
| `200` | Replay or duplicate; the original record is returned |
| `401` | Missing or expired token |
| `403` | Token lacks `evidence.write` or is not client-bound |
| `422` | Hash mismatch, unknown evidence type or invalid payload |

Deduplication is per `(client, external id)`. A new snapshot needs a new `id`;
do not reuse an id for changed counts.

## Failure handling (producer)

- `2xx` and `409`: delivered.
- `400/401/403/404/422`: permanent; surface to an operator, do not retry.
- `429`: retry, honouring `Retry-After`.
- `5xx`/network: retry with exponential backoff and jitter.

A failed delivery is a stale measurement, not a clean control. Keep the previous
snapshot visible and let its age show.

## Operating it

1. Mint a scoped, client-bound token for the producer:
   ```bash
   php artisan opengrc:token:create detection@example.com \
     --name=opendetectionengine \
     --client=acme \
     --ability=evidence.write
   ```
2. Send a snapshot after each rule deployment or validation run.
3. Create the validation rule and link it to the monitoring control.
4. Review the evidence browser and the assurance matrix; treat a failing rule as
   a finding, not a badge.

## Limits

- No rule execution, conversion or authoring in OpenGRC.
- No per-rule history in this slice; the detection platform owns it.
- No raw YARA strings, Sigma logic, samples or log lines.
- No write-back to the detection platform.
- Coverage is not effectiveness. A rule can be active and wrong; validation and
  exceptions are part of the reading.
