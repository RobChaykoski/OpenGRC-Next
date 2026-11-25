# AI agent runtime and sensor evidence

Status: **Prismor receipts and attestation bundles, Pipelock action receipts and
hash-verified sensor summaries implemented**; the wider design is accepted
([ADR 0060](../decisions/0060-ai-agent-runtime-evidence.md)). This page defines
the boundary, the evidence contract, which sources are suitable, and the sources
wired today. The push endpoint is not implemented.

## Why this is not another dashboard

An agent gateway, egress firewall or network sensor already shows what it
blocked. That console is useful to the operator and useless to an auditor unless
the decision can be verified, scoped and linked to a control. OpenGRC's job is
the second part: keep the control, the evidence request, the review, the expiry
and the finding. It does not mediate the traffic and it does not store it.

The rule from the DLP boundary applies again: a match label and a digest are
evidence; the matched value is not.

```text
agent / MCP client
  -> gateway, firewall or sandbox (enforcement, raw telemetry)
  -> signed receipt, hash-verified export or aggregate telemetry
  -> OpenGRC evidence record (decision, scope, signature, control link)
  -> validation rule or reviewer -> control state / finding
```

## Source landscape

| Source | License | Maintenance | Evidence surface | Role in OpenGRC |
|---|---|---|---|---|
| Prismor | Apache-2.0 | Active | Hash-chained, Ed25519-signed audit trail; signed telemetry receipts; signed attestation bundle with framework coverage | Preferred signed source |
| Pipelock | Apache-2.0 | Active | Mediator-signed action receipts; hash-chained flight recorder; SIEM export | Preferred signed source |
| DefenseClaw | Apache-2.0 | Active | Capability scan and runtime inspection; durable audit-evidence export | Hash-verified export |
| agentgateway | Apache-2.0 | Active | OpenTelemetry metrics, logs and traces; policy decisions in logs | Aggregate telemetry, low confidence |
| Suricata | GPL-2.0 | Active | EVE JSON alerts, flows, protocol events and sensor stats | Aggregate detector and sensor health |
| Arkime | Apache-2.0 | Active | Full PCAP capture, indexed session metadata, viewer and API | Aggregate session and retention evidence; no PCAP |
| Cilium Hubble | Apache-2.0 | Active | Kubernetes L3/L4/L7 flow visibility, service map and verdicts | Aggregate flow and service-edge counts; no raw flows or payloads |
| ruby-nmap | MIT | Active | nmap XML parser and command wrapper | Lab tool; summary only |
| Peplum / Peplum::Nmap | MIT-style | Inactive since 2023 | Distributed nmap output | Lab reference only; not a dependency |
| Trylon Gateway | BSL 1.1 | Active | Policy decisions and redaction | Not a dependency; reference only |

A source is not selected because it has the most features. It is selected because
its evidence can be verified, its licence is acceptable, and its default export
is least-data. A vendor's framework coverage is a claim to review, not a
completed crosswalk.

## What OpenGRC accepts

| Field | Example | Why |
|---|---|---|
| Source system and version | `pipelock` / `3.8.0` | Reproducibility and known behaviour |
| Evidence type | `action-receipt`, `attestation`, `export`, `sensor-summary` | Decides the verification rule |
| Evidence id | receipt or bundle identifier | Dedupe and replay detection |
| Observed at | RFC 3339 timestamp | The window the decision covers |
| Scope | agent id, tool, policy, sensor, target scope | What the evidence does and does not cover |
| Decision | allow, block, redact, ask, quarantine | The outcome, not the feature name |
| Policy reference | policy id or SHA-256 | What rule produced the decision |
| Actor reference | source-side agent or user id | Attribution without pretending it is an OpenGRC user |
| Counts | blocked 4, allowed 1,312, window 1h | Aggregate evidence without content |
| Signature | algorithm, key fingerprint, value | Verification and signer identity |
| Bundle digest | SHA-256 of the exported bytes | Integrity when there is no signature |
| Confidence | high / medium / low | Set by evidence class, not by the vendor |
| Expiry | review-after date | A silent source must become a finding |

Never accepted: prompts, completions, tool arguments or results, HTTP bodies,
credential-bearing headers, raw URLs with query values, secrets, packet payloads,
packet captures, scan target lists, or source credentials.

## Source profiles

### Prismor

Import the receipt or attestation, the signer fingerprint, the decision, the
policy reference, the agent identity and the covered window. Verify the Ed25519
signature against a pinned key before the record is accepted. The attestation
bundle's framework coverage (OWASP LLM/Agentic, NIST AI RMF, EU AI Act) is stored
as a claim with its date and source, and linked to a reviewed crosswalk; it does
not become control coverage by itself.

The receipt import is implemented:

```bash
php artisan opengrc:evidence:prismor path/to/receipt.json \
  --pubkey=<base64-ed25519-public-key> \
  --control=A.5.15 \
  --source=Prismor
```

- The file may be one receipt, a JSON array, or JSONL.
- The command recomputes the chain hash, verifies the Ed25519 signature against
  the pinned key, and checks `signing_key_id` against that key.
- It refuses the batch if any receipt fails or a chain link is broken. A sequence
  gap is reported, not rejected.
- Re-importing the same `device_id` + `event_id` is a duplicate, not a new record.
- A re-signed receipt with the same identity but different bytes is a conflict,
  not a duplicate.
- `--allow-unpinned` verifies against the inline key and stores the evidence with
  `pinned: false` and low confidence. Use it for a demo, not an audit.
- `--dry-run` verifies and reports without writing. The stored payload contains
  the decision metadata, hashes and signature; it does not contain the tool
  input, prompt, output or a command line.

The import stores an immutable `EvidenceRecord` with type
`agent-action-receipt` under an `EvidenceSource` of type Prismor. The record
status is `Unknown`: a blocked action is evidence that the boundary operated, not
an automatic control verdict. A validation rule or reviewer decides.

The signed attestation bundle import is also implemented:

```bash
php artisan opengrc:evidence:prismor-attestation path/to/attestation.json \
  --pubkey=<base64-ed25519-public-key> \
  --control=A.5.15 \
  --source=Prismor
```

- The command recomputes the SHA-256 of the JCS-canonical bundle body, verifies
  the Ed25519 signature over `{hash, ts, identity}`, and checks the key id.
- `--allow-unpinned`, `--dry-run`, `--control`, `--asset` and `--json` work as
  they do for receipts.
- The stored payload keeps the agent inventory (name, framework, mode, enabled,
  last seen), the discovery summary, framework/control IDs and their covered
  flag, audit-finding counts by severity and category, the trail checkpoint and
  the signature. It does **not** keep audit messages, raw MCP URLs, provider-key
  locations or the bundle body.
- The evidence type is `agent-runtime-attestation`. Framework coverage is a
  vendor claim with a date and signer; it is not control coverage until a
  reviewed crosswalk maps it.
- A bundle body edit breaks the content hash; a device-id edit breaks the
  signature as well. A re-signed bundle with the same content hash but a
  different signature is a conflict, not a duplicate.

There is still no first-class agent inventory object: the attestation's
inventory is evidence metadata, not an asset record. That is the remaining
design work after the import.

### Pipelock

Import the action receipt and the verdict, with the receipt id, signer
fingerprint, transport, policy hash and timestamp. Verify with the operator's
public key, offline. The flight recorder is hash-chained; record the chain
position and the verification result. Do not import the request URL, body,
headers or any redacted value. A demo receipt signed with an ephemeral key is
self-consistent but not tied to an operator identity; label it accordingly.

The receipt import is implemented:

```bash
php artisan opengrc:evidence:pipelock path/to/receipt.json \
  --pubkey=<64-hex-ed25519-signer-key> \
  --control=A.8.16 \
  --source=Pipelock
```

- The file may be one receipt, a JSON array, or JSONL.
- The verifier reproduces Pipelock's Go-ordered canonical JSON for the action
  record (declaration order, `omitempty`, compact output, HTML-safe escaping)
  and checks Ed25519 over the SHA-256 digest. Unknown action fields fail closed.
- The target is stored as its scheme/host/port plus a SHA-256 of the full
  target. The path and query are not copied.
- `--allow-unpinned` verifies against the inline signer key and stores low
  confidence; `--dry-run` verifies without writing.
- The batch is replayed as a chain. `chain_seq` starts at 0 and increments by one,
  and `chain_prev_hash` is the hex SHA-256 of the previous receipt's canonical
  envelope (`version`, `action_record`, `signature`, `signer_key`), or `genesis`
  for the first receipt. The canonical envelope is recomputed, so the chain binds
  the signature and the signer key, not only the action record.
- A signing-key rotation is replayed when every segment key is pinned. Pass the
  keys comma-separated (`--pubkey=A,B`) or one per line in `--pubkey-file`. A
  rotated segment starts at `chain_seq` 0 with a `key_transition` marker that
  names the prior signer, its final `chain_seq` and the prior tail hash, and the
  segment's `chain_prev_hash` must equal that tail. The marker is signed by the
  new key, so it is continuity metadata, not trust delegation: a segment is
  accepted only because its key is in the pinned set. A batch that starts at a
  rotation boundary is refused, because the prior tail is not present.
- A chain that links from genesis is imported with `chain_verified: true`, and
  each record stores its own `chain_hash` next to `chain_prev_hash`, so the next
  import can be correlated without trusting a filename. Within a segment one
  signer is used; a signer change without a rotation marker is rejected rather
  than spliced in.
- A broken link, a sequence gap or an out-of-order batch rejects the whole batch.
  A mid-chain slice is refused unless `--allow-partial-chain` is passed; those
  records are stored with `chain_verified: false` and `chain_scope: partial`,
  because ancestry back to genesis cannot be shown. Records are append-only, so a
  later import of the whole run does not upgrade an earlier partial record; it
  adds the missing receipts and leaves the earlier verdict as it was.
- Chain replay proves the receipts present were written by the key that signed
  them, in order, from genesis; only the pinned key set says who that is. It does
  not prove the last receipt is the run's last: a truncated export looks like a
  shorter run, and a rotation boundary whose prior segment is absent is refused
  rather than assumed.
- Stored fields are the action id/type, timestamp, principal, actor, verdict,
  transport, method, policy hash, side-effect/reversibility classes, data-class
  labels, decision phase, layer/pattern/severity, session/request ids, the
  redacted target and the signature. Prompts, tool arguments, request bodies,
  target paths/queries and redaction values are not copied.

### DefenseClaw

Ask for the export in a documented format and hash the exact bytes received.
Record the exporter version, scope, time and bundle digest. If the export
contains raw command lines, prompts or tool arguments, reject it or reduce it
before import; the connector must not become a second raw store.

### agentgateway

Use OpenTelemetry metrics and traces for aggregate request, policy-decision and
error counts. This is operational telemetry, not a signed decision record. Store
counts and identifiers with a low-confidence marker; do not present a log line as
proof that a specific request was blocked.

### Suricata

Import aggregate alert counts by signature or category, flow summaries by service
and direction, and sensor health (last event, capture drops, rule load, uptime)
over a stated window. Use the community-id to correlate records across sensors.
Do not import payloads, packet data, raw URLs or full flow records. A signature is
a detector, not a verdict: a validation rule or reviewer decides what a count
means for the control. See ADR 0058 for the flow-telemetry boundary.

### Arkime

Import aggregate session counts by protocol, service and direction, sensor and
retention health, and a reference to the Arkime query or session. Do not import
PCAPs, payloads, raw session JSON (which contains URLs, queries and headers),
credentials or user identities beyond the source's own pseudonymous id. Arkime
is not an IDS: a session is a record that traffic occurred, not a verdict.

A bounded capture is an investigation artifact. If it must be attached, follow
the forensic evidence rules in ADR 0019 and keep the PCAP in Arkime; the GRC
record holds the summary and the link. Do not make the application database a
packet store.

### Cilium Hubble

Import aggregate flow counts by namespace, service, verdict and protocol, plus
service-map edges and sensor health for a stated window. Do not import raw flows,
DNS query names, HTTP URLs, process data or payloads. Hubble UI is a read-only
investigation surface, not the GRC console; use Hubble metrics or a read-only API
snapshot as the connector source. See the network-flow boundary in
`network-flow-telemetry.md` and ADR 0058.

### nmap, ruby-nmap and Peplum

These are scanning tools for a lab or an authorised assessment. OpenGRC accepts a
scan summary: scanner identity, authorisation reference, target scope as a CIDR
or range, host and service counts, window and result digest. It does not accept a
target list, banners, NSE output bodies or credentials. Peplum and Peplum::Nmap
have had no release since 2023 and are not a product dependency; ruby-nmap is
maintained but still belongs with the lab tooling, not inside the web container.

### Trylon Gateway

BSL 1.1 is source-available and outside the dependency allowlist. It can be named
in a comparison or a lab. An operator who uses it attaches the vendor's export to
the relevant control by hand; OpenGRC does not ship a connector.

## Sensor summary contract

Suricata, Arkime and Cilium Hubble share one import command for aggregate
summaries:

```bash
php artisan opengrc:evidence:sensor-summary path/to/summary.json \
  --sha256=<sha256-of-the-exact-file> \
  --control=A.8.16 \
  --source=Suricata
```

The input is a normalised object:

```json
{
  "schema": "opengrc.sensor-summary.v1",
  "source": "suricata",
  "sensor": "edge-01",
  "window": {"from": "2026-11-24T00:00:00Z", "to": "2026-11-24T23:59:59Z"},
  "counts": {"events": 1200, "alerts": 14, "blocked": 3},
  "by_verdict": {"allowed": 1183, "blocked": 3, "warned": 14},
  "by_protocol": {"tcp": 1000, "udp": 200},
  "by_service": [{"name": "https", "count": 800}],
  "edges": [{"from": "dmz", "to": "internet", "count": 700}],
  "health": {"last_event_at": "2026-11-24T23:59:57Z", "capture_drops": 0, "uptime_seconds": 86400}
}
```

Only those keys are stored. Extra fields (raw events, URLs, payloads) are
ignored by the importer, not copied. `--sha256` must match the exact file bytes;
without it, `--allow-unhashed` stores the summary with low confidence. A
hash-verified file is medium confidence: it proves the bytes matched the hash the
operator supplied, not who produced them. The evidence type is
`sensor-summary`, the record status is `Unknown`, and a validation rule or
reviewer decides what the counts mean for the control.

## Control mapping

| Outcome | Control area | Evidence |
|---|---|---|
| Only approved models and tools are used | AI usage policy, asset inventory | Model artifact approval, agent inventory, tool allowlist |
| Sensitive data does not leave the boundary | DLP, acceptable use | Egress block counts, signed receipts, DLP finding labels |
| Tool calls are authorised and reviewable | Access control, change management | HITL/approval records, policy hash, decision counts |
| Monitoring is operating | Logging and monitoring, network security | Sensor health, coverage window, last-seen time |
| Failures become work | Incident handling, remediation | Finding, owner, due date, linked risk |

NIS2 Article 21(2) measures for policy, supply chain, access control and secured
communications, the ISO/IEC 27001 logging and access-control outcomes, and the
equivalent NIST CSF functions are the usual destinations. Record the mapping
with a rationale and a reviewer; do not map by feature name.

## Operating procedure

1. **Inventory the boundary.** Record which gateway, sensor and scanner is in the
   path, who owns it, and which agents or ranges it covers.
2. **Pin the key.** Obtain the signing key out of band. Do not fetch it from the
   source at import time; a compromised source must not be able to replace its
   own trust anchor.
3. **Verify before storing.** Verify the canonical bytes and the signature or the
   bundle digest. Reject duplicate receipt ids and broken chain positions.
4. **Reduce.** Keep the decision, scope, counts and identifiers. Drop content
   before it reaches the database, not after.
5. **Link.** Attach the evidence to a control with a rationale. A finding can be
   linked to the risk and the policy that owns it.
6. **Expire.** A source with no evidence inside its review window becomes a
   finding. Silence is not coverage.
7. **Review.** The reviewer sees the signature result, the scope and the gap
   between the claimed and observed coverage. A vendor's coverage claim is
   reviewed, not accepted.

## Failure modes

- **A dashboard becomes the source of truth.** The screenshot is not evidence;
  the signed record or hash-verified export is.
- **Key replacement.** A source that supplies its own public key can re-sign a
  rewritten history. Pin the key and record rotation as a change.
- **Replay and duplication.** Receipt ids and chain positions must be unique; a
  replayed block inflates a count.
- **Scope drift.** A gateway that covered one agent last quarter may cover ten
  now. The evidence record stores the scope it observed, not the current scope.
- **False precision.** An unsigned count is not a decision record; label it low
  confidence and do not let it close a control.
- **Content creep.** An exporter that includes prompts or command lines will fill
  the database with the data the boundary was protecting. Reduce or reject it.

## Limits, stated plainly

- A signature proves the key holder's boundary made the decision. It does not
  prove the operator is honest, that the agent stayed inside the boundary, or
  that the tool saw every egress path.
- Agent identity is not standard across tools. The import keeps the source's
  identifier and does not merge it with an OpenGRC user.
- These sources produce decision evidence, not control effectiveness. A control
  is still tested by a person or a validation rule.
- Prismor receipts and attestation bundles, Pipelock action receipts with chain
  replay (including pinned signing-key rotation), and hash-verified sensor
  summaries are implemented. The push endpoint, direct vendor connectors and a
  first-class agent inventory remain future work. Until they exist, attach
  another source's export to a control, record the hash and time, and keep raw
  content out of the record.
- A packet-capture store such as Arkime proves that a session existed, not that a
  control worked. Its value is the aggregate and the link for an investigator.

## Related

- [ADR 0060: agent runtime and sensor evidence is imported, not mediated](../decisions/0060-ai-agent-runtime-evidence.md)
- [ADR 0019: forensic evidence handling](../decisions/0019-forensic-evidence-handling.md)
- [ADR 0054: streaming evidence is a push contract](../decisions/0054-streaming-evidence-contract.md)
- [ADR 0058: network-flow telemetry feeds a risk graph](../decisions/0058-network-flow-telemetry-risk-graph.md)
- [ADR 0059: AI model artifacts](../decisions/0059-ai-model-artifact-register.md)
- [Network-flow telemetry](network-flow-telemetry.md)
- [Detection coverage](detection-coverage.md)
- [Data loss prevention](../security/data-loss-prevention.md)
- [NIS2 readiness](../grc/nis2-readiness.md)
