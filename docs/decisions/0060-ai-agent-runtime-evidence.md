# 0060 - Agent runtime and sensor evidence is imported, not mediated

- Status: accepted
- Date: 2026-11-24
- Implementation note (2026-11-24): the first slice is implemented as
  `opengrc:evidence:prismor`, `opengrc:evidence:prismor-attestation` and
  `opengrc:evidence:sensor-summary`. Prismor receipts and attestation bundles
  are verified with a pinned Ed25519 key; aggregate sensor summaries from
  Suricata, Arkime or Hubble are imported with an optional file hash. Pipelock
  and the push endpoint remain future work.

## Context

Agentic systems now sit behind a control plane: an MCP or A2A gateway, an egress
firewall, a tool-call policy engine, or an LLM guardrail. Those tools make
allow, block, redact and approval decisions at runtime, and several of them emit
signed records of what they decided. Network sensors do the same for packets and
flows.

OpenGRC can hold the control, the risk, the policy and the finding. It cannot sit
in the request path, inspect prompts or tool arguments, run a scanner, or store
packet payloads. The question is what evidence crosses the boundary into the GRC
record, and what stays with the enforcement system.

The candidates differ in evidence quality, not only features:

| Tool | License | Evidence surface |
|---|---|---|
| Prismor | Apache-2.0 | Hash-chained, Ed25519-signed audit trail; signed telemetry receipts; signed attestation bundle with framework coverage |
| Pipelock | Apache-2.0 | Mediator-signed action receipts; hash-chained flight recorder; SIEM export |
| DefenseClaw | Apache-2.0 | Capability scan and runtime inspection with durable audit-evidence export |
| agentgateway | Apache-2.0 | OpenTelemetry metrics, logs and traces; policy decisions in logs |
| Trylon Gateway | BSL 1.1 | Policy decisions and redaction; source-available, not OSI-approved |
| Suricata | GPL-2.0 | EVE JSON alerts, flows, protocol events and sensor stats |
| Arkime | Apache-2.0 | Full PCAP capture, indexed session metadata, viewer and API |
| Peplum / Peplum::Nmap | MIT-style; inactive since 2023 | Distributed nmap output; no governance evidence surface |
| ruby-nmap | MIT | nmap XML parsing; a lab tool, not a service |

A dashboard screenshot is not evidence. A signed receipt whose signature and
scope can be checked, linked to a control, is.

## Decision (proposed)

### 1. OpenGRC remains the GRC authority

The enforcement tool owns the decision and the raw telemetry. OpenGRC owns the
control, the evidence request, the review, the exception and the finding. An
imported record does not become a control verdict by itself; a validation rule or
a person decides that.

### 2. Import the decision record, not the traffic

Permitted evidence:

- action type, verdict (allow, block, redact, ask, quarantine), policy identifier
  or hash, timestamp, source and agent identity where the source provides one;
- counts and aggregates over a stated window;
- signature, signer key fingerprint, receipt identifier and chain position;
- sensor health, coverage and rule or detector identifiers;
- a hash of the exported bundle and the export time.

Never imported: prompts, completions, tool arguments or results, HTTP bodies,
headers carrying credentials, raw URLs with query values, secrets, packet
payloads, packet captures, scan target lists, or credentials for the source.

The DLP rule is the same one already applied to AI egress: a match label and a
digest are evidence; the matched value is not.

### 3. Prefer signed evidence, then hash-verified exports, then telemetry

| Evidence class | Example | Treatment |
|---|---|---|
| Signed receipt or attestation | Prismor receipt, Pipelock action receipt | Verify the signature and key; store receipt id, signer fingerprint, verdict and scope. High confidence. |
| Hash-verified export | DefenseClaw audit export, signed CSV/JSON snapshot | Record the bundle hash, source, time and scope. Medium confidence. |
| Unsigned telemetry | agentgateway OTel metrics, Suricata EVE aggregates | Accept counts and identifiers with a low-confidence marker; do not present it as proof of a specific decision. |

A signature proves the key holder's boundary made the decision. It does not prove
the operator is honest, that the agent stayed inside the boundary, or that the
tool saw every path. That limit is recorded with the evidence.

### 4. Use the existing evidence boundary

- Receipts and attestations arrive through the signed push contract of ADR 0054,
  or through a read-only API with a hash-verified snapshot, following ADR 0009.
- OpenGRC does not host an MCP server, an agent gateway, or a scanner. ADR 0006
  removed MCP from the community edition and this decision does not reverse it.
- A packet-capture store such as Arkime is not an evidence store for OpenGRC.
  The GRC record holds the session summary, retention state and a reference; the
  PCAP stays in Arkime. A bounded capture attached to an investigation follows
  the forensic evidence rules in ADR 0019, not this import.
- A source that cannot sign or hash is still usable as a lead, not as evidence.

### 5. Tool selection gates

1. License is on the allowlist in `docs/OSS-POLICY.md`. BSL and other
   source-available licences are reference material, not dependencies.
2. The project is maintained: a recent release or commit, an issue tracker that
   answers, and a security policy.
3. The evidence has a stable identifier and a verifiable signature or hash.
4. The default export is least-data; raw content is off unless a deployment
   explicitly enables it.
5. The tool can be replaced without rewriting the control mapping.

Trylon (BSL 1.1) and Peplum / Peplum::Nmap (inactive since 2023) fail these gates
as dependencies. They can be named in a lab or a comparison, not linked into the
product. A licence file that GitHub cannot classify is checked by hand before it
is treated as approved.

The gates apply to code that is linked or shipped with OpenGRC. A separately
deployed service that exports data is not a dependency, but its licence, export
terms and data handling are still reviewed before a connector is built.

### 6. Map to controls by outcome, not by vendor feature

| Outcome | Control area | Evidence |
|---|---|---|
| Agents use only approved models and tools | AI usage policy, asset inventory | Model artifact approval, agent inventory, tool allowlist |
| Sensitive data does not leave the boundary | DLP, acceptable use | Egress block counts, signed receipts, DLP finding labels |
| Tool calls are authorised and reviewable | Access control, change management | Approval/HITL records, policy hash, tool-call decision counts |
| Monitoring is operating | Logging and monitoring, network security | Sensor health, coverage window, last-seen time |
| Failures become work | Incident handling, remediation | Finding, owner, due date, linked risk |

The mapping is recorded with a rationale and a reviewer, as with any crosswalk.
A vendor's built-in framework coverage is a claim to review, not a completed
mapping.

## Consequences

- An auditor can ask "what did the boundary decide, under which policy, and can
  I verify it?" and get a dated, signed answer instead of a screenshot.
- OpenGRC does not become a prompt store, a packet store, or a second agent
  console.
- Every source brings a schema, a key, and an expiry. The operational cost is key
  distribution, replay protection and evidence freshness, not storage.
- A signed receipt from a compromised operator key is still signed. The trust
  anchor and the key lifecycle are part of the control, not an afterthought.
- Unsigned telemetry is useful for drift and coverage, but it must be labelled,
  or a dashboard will turn a log line into a false assurance.
- Agent identity is inconsistent across tools. The import stores the source's
  actor identifier and does not pretend it is an OpenGRC user.

## Out of scope

- Running the gateway, proxy, sandbox, scanner or sensor.
- Storing prompts, completions, tool arguments, request bodies or packet payloads.
- Executing allow/block policy inside OpenGRC.
- Replacing the SIEM or the agent dashboard.
- Scoring a vendor's framework coverage as if it were an independent audit.

## Phased plan

1. **Contract.** Publish the evidence schema, signature verification rules and
   the fields that are never accepted.
2. **One signed source.** Connect Prismor or Pipelock receipts, verify the key,
   and link decisions to one control with a validation rule.
3. **Sensor summaries.** Import Suricata EVE aggregates and sensor health, not
   raw events, and show the coverage window.
4. **Agent inventory.** Register agents, their owner, approved models and tools,
   and the gateway policy in force.
5. **Review loop.** Add an attestation or coverage review with an expiry, so a
   silent source becomes a finding rather than a green dashboard.

## References

- ADR 0006: MCP removed from the community edition
- ADR 0009: continuous control monitoring
- ADR 0019: forensic evidence handling
- ADR 0028: SBOM and OSV are supply-chain evidence, not a scanner
- ADR 0054: streaming evidence is a push contract
- ADR 0056: the audit ledger
- ADR 0058: network-flow telemetry feeds a risk graph
- ADR 0059: AI model artifacts are registered, not copied
- `docs/integrations/ai-agent-runtime.md`
