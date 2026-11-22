# 0054 — Streaming evidence is a push contract, not a broker inside OpenGRC

- Status: accepted
- Date: 2026-11-21

## Context

Every connector today is polled: the scheduler runs `opengrc:evidence:collect`, a
connector reads a vendor API, and evidence is written after the fact. That is the
right shape for posture — patch state, SCA results, catalog classification — and
the wrong shape for a control that fails the moment an event happens.

The tempting design is to put a stream processor in OpenGRC: consume Kafka, keep
per-source cursors, evaluate rules on the event, page a human. That makes the
system of record a message broker, duplicates the SIEM, and makes an OpenGRC
outage an alerting outage.

## Decision (proposed)

1. **A separate stream service owns subscription and buffering.** OpenGRC does
   not connect to a broker and does not hold a consumer group. The producer side
   (a connector, a SIEM export, or a collector) buffers, retries and orders.
2. **Push uses the same evidence contract.** A new endpoint accepts the existing
   hash-verified envelope with a `stream` block:

   ```json
   {
     "id": "evt-2026-11-21-0001",
     "sha256": "<digest of evidence>",
     "evidenceType": "detection-event",
     "stream": {
       "name": "edr-alerts",
       "cursor": "0000000000000421",
       "eventTime": "2026-11-21T09:14:03Z"
     },
     "evidence": { "...": "aggregate fields only" }
   }
   ```

   One event or a bounded batch per request. The payload rule is unchanged: no
   raw log lines, no content, no credentials. A detection event is summarised the
   same way a detection-coverage snapshot is.
3. **Delivery is at-least-once, with idempotency at the edge.** The dedupe key is
   `(client, stream, cursor)`; a payload hash is the fallback when a producer
   cannot supply a cursor. Ordering is per stream, never global. The endpoint
   verifies the hash and the HMAC, persists, and returns `202` with a receipt
   id and the correlation id. A duplicate returns the original receipt.
4. **Late and skewed events are labelled, not trusted.** An `eventTime` outside
   a configured window is stored with the skew recorded and contributes `unknown`
   to a validation rule until a human or a rule accepts it.
5. **Rejections are counted, not stored.** A failed HMAC, a bad schema or an
   oversized body returns 4xx and increments a rejection counter with the reason
   and the source. The body is never written, logged or echoed; it may itself be
   sensitive.
6. **Alerting stays outside.** OpenGRC records the event and its control effect.
   Paging, escalation and on-call belong to the detection platform or the SIEM.
7. **The endpoint is off by default.** Enabling it is a deliberate configuration
   change, requires the same scoped, client-bound token and platform access rules
   as the other machine endpoints, and has its own rate limit.

### Implementation

`evidence_streams` holds one row per `(client_id, name)` with an encrypted
secret; `php artisan opengrc:evidence:stream:create --client=... --name=...`
prints it once. The endpoint is `POST /api/v1/evidence/stream`, uses the existing
`evidence.write` token, and returns 404 unless `EVIDENCE_STREAM_ENABLED=true`.

The signature header is `X-OpenGRC-Signature: t=<unix>,v1=<hmac_sha256(t.body)>`
with a 300-second tolerance. The evidence type is `detection-event`; the payload
is stored as `{source, stream, sha256, event, receivedAt}` so a validation rule
targets `event.*`. A duplicate cursor returns the original record; a reused cursor
with a different payload is `409`; every rejection increments the stream's
counters and never stores the body.

## Consequences
- Time-sensitive evidence can reach the system of record in seconds without
  OpenGRC becoming a broker.
- The system still cannot alert. A control that needs paging in under a minute is
  only as fast as the SIEM, and that is the correct boundary.
- Per-stream ordering is the only guarantee. A cross-stream timeline is a
  reporting problem, not an ingestion promise.
- A public push endpoint is a new attack surface: HMAC with a timestamp window,
  body-size and schema limits, per-client rate limiting, IP rules, and a negative
  test for each. It must not accept a session cookie.
- The push contract is a versioned API. A breaking change to the envelope is a new
  endpoint version, not a field rename.

## Alternatives considered

- **Kafka consumer inside OpenGRC.** Rejected: it makes the system of record
  operationally coupled to a broker and duplicates the SIEM.
- **Database polling of a vendor queue.** Rejected as "streaming": it is the
  existing scheduled connector with extra steps.
- **Store raw events for replay.** Rejected: raw log lines are exactly what the
  evidence standard excludes.
- **Let the collector write directly to the database.** Rejected: it bypasses the
  hash check, the dedupe key and the ledger.

## References

- ADR 0009 — continuous control monitoring and vendor-neutral evidence connectors
- ADR 0017 — the one POST exception for connector authentication
- ADR 0047 — platform access rules
- `docs/grc/evidence-standard.md` — what an evidence payload may contain
