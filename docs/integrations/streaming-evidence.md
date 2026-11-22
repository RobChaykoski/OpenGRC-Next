# Streaming evidence

Time-sensitive evidence can be pushed instead of polled: a signed
`detection-event` from a producer that already sees the event (a SIEM export, an
EDR webhook relay, a collector). The contract is ADR 0054; this page is the
operator runbook.

The endpoint is **off by default**. Enabling it is a deliberate change.

## What it is for

Polling is right for posture: patch state, SCA results, catalog classification.
It is wrong for an event that must be in the record close to when it happened.
The push path accepts one summarised event (or a bounded batch) and writes it as
evidence with status `unknown`; a validation rule decides the control effect.

It is not an alerting path. Paging stays in the SIEM or the detection platform.

## 1. Enable and create a stream

```dotenv
EVIDENCE_STREAM_ENABLED=true
```

```bash
php artisan opengrc:evidence:stream:create \
  --client=acme-soc \
  --name=edr-alerts
```

The command prints the signing secret once. A stream belongs to one client; the
API token must be bound to the same client and carry `evidence.write`.

Rotate by disabling the old stream, creating a new name, cutting the producer
over and deleting the old row.

## 2. Sign the request

The signature covers the exact bytes you send:

```
X-OpenGRC-Signature: t=<unix-seconds>,v1=<hex hmac_sha256(t + "." + rawBody)>
```

A request outside `EVIDENCE_STREAM_TOLERANCE` (default 300 seconds) is rejected,
which is what stops a captured request being replayed later.

```python
import hashlib, hmac, json, time, requests

body = {
    "id": "evt-0001",
    "sha256": hashlib.sha256(json.dumps(evidence, sort_keys=True, separators=(",", ":")).encode()).hexdigest(),
    "evidenceType": "detection-event",
    "evidence": evidence,
    "stream": {"name": "edr-alerts", "cursor": "0000000000000421", "eventTime": "2026-11-21T09:14:03Z"},
}
raw = json.dumps(body, separators=(",", ":")).encode()
ts = str(int(time.time()))
sig = hmac.new(secret.encode(), ts.encode() + b"." + raw, hashlib.sha256).hexdigest()

requests.post(
    "https://grc.example.com/api/v1/evidence/stream",
    data=raw,
    headers={
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "X-OpenGRC-Signature": f"t={ts},v1={sig}",
    },
    timeout=10,
)
```

The `sha256` is over the canonicalised `evidence` object (keys sorted
recursively, no whitespace), the same rule as the snapshot API.

## 3. Choose the cursor and the event time

- **Cursor** is the producer's position in its own stream, not a global clock.
  It is the identity: replaying it returns the original record, and reusing it
  with a different payload is a `409`.
- **Ordering** is per stream, not global. OpenGRC does not reorder late events.
- **Event time** outside `EVIDENCE_STREAM_MAX_SKEW_HOURS` (default 24) is
  rejected with `late: true`. Fix the producer clock rather than widening the
  window without a reason.
- The received time is stored alongside the event time; the gap is visible.

## 4. Response codes

| Code | Meaning |
|---|---|
| 202 | Accepted and written |
| 200 | Duplicate cursor; the original `evidenceId` is returned |
| 401 | Missing token, or a signature/timestamp failure |
| 403 | Unknown or inactive stream, or a token without `evidence.write` |
| 404 | The endpoint is disabled (checked after authentication) |
| 409 | Cursor reused with a different payload |
| 422 | Schema, hash, evidence type or event-time problem |

Rejections increment `rejected_count` and set `last_rejection_reason` on the
stream. The body is never stored for a rejection; only the counter and the reason.

## 5. What the record contains

```json
{
  "source": "stream",
  "stream": {"name": "edr-alerts", "cursor": "0000000000000421", "eventTime": "..."},
  "sha256": "...",
  "event": { "...": "the producer's summary" },
  "receivedAt": "..."
}
```

The payload rule is unchanged: aggregate fields only. No raw log lines, no
request bodies, no file content, no credentials. A rule that needs a specific
field targets `event.<field>`.

The record is written against a source named `Stream: <client>/<stream>` and has
status `unknown`; the validation rule decides effective or failed.

## 6. Test matrix

| Case | Expected |
|---|---|
| Valid signed event | 202, one record, cursor and counters updated |
| Replay the same cursor | 200, same `evidenceId`, still one record |
| Same cursor, new payload | 409, rejection counted |
| Wrong secret | 401, rejection counted |
| Timestamp 10 minutes old | 401 |
| Tampered body | 422 (hash or signature) |
| Event time 3 days old | 422, `late: true` |
| Unknown or disabled stream | 403 |
| Endpoint disabled | 404 |
| Token without `evidence.write` | 403 |

## Limits

- One event or a bounded batch per request; the whole body is size-limited by
  the web server.
- No cross-stream ordering, no reply/ack channel beyond the response.
- The producer must buffer and retry on 5xx with the same cursor.
- OpenGRC records the event's control effect; it does not page anyone.
