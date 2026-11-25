# 0058 - Network-flow telemetry feeds a risk graph; OpenGRC stays the GRC authority

- Status: proposed
- Date: 2026-11-23

## Context

OpenGRC can prove that a control is documented, tested, and evidenced. It cannot
show which assets actually talk to each other, which paths cross a trust
boundary, or whether a failing control sits on a live traffic path.

The OpenCompliance endpoint can enable NetFlow, sFlow, IPFIX, and other network
telemetry, monitor it in real time, and select the traffic that matters for
analysis in the central console. OpenMetadata already holds asset ownership,
lineage, classification, and service context. The missing piece is a projection
that joins those sources without turning OpenGRC into a packet store or a graph
database.

ADR 0054 established the boundary for streaming evidence: OpenGRC accepts a
signed push contract and does not run a broker inside the application. This ADR
applies the same rule to flow telemetry.

## Decision (proposed)

### 1. OpenCompliance is the collection endpoint

The OpenCompliance endpoint is the collection and selection point:

- It enables **NetFlow v9, IPFIX, sFlow**, eBPF flow observation, and cloud flow
  logs where the deployment supports them.
- It exposes **metadata-only flow records**: source, destination, ports, protocol,
  direction, bytes, packets, first/last seen, sampling rate, and the observing
  interface or sensor.
- It supports **real-time monitoring** and **traffic selection**. A selector can
  export a flow, request a bounded packet capture, or suppress a noisy flow.
- Where Kubernetes is in scope, it can read aggregate **Cilium Hubble** flow and
  service-map data. Hubble UI is a read-only investigation view, not the GRC
  console or a system of record.
- It does **not** become the system of record for controls, risks, or assets.

### 2. The pipeline is decoupled

```text
OpenCompliance endpoint
  -> flow collector (goflow2, ntopng, Zeek, or the endpoint's own collector)
  -> broker (Kafka, Redpanda, or NATS JetStream)
  -> enricher (OpenMetadata tags + OpenGRC control and risk state)
  -> graph store (Memgraph or Neo4j)
  -> central console (OpenGRC read-only projection)
```

The broker and graph store are **separate services**, not tables in the OpenGRC
application database. A flow outage must not stop the GRC console, and a GRC
migration must not stop telemetry.

### 3. Authority is unchanged

| System | Owns | Does not own |
|---|---|---|
| OpenGRC | Controls, frameworks, risks, exceptions, findings, evidence state, approvals | Packet capture, flow retention, graph queries |
| OpenMetadata | Asset identity, ownership, lineage, classification, service metadata | Control status or risk acceptance |
| OpenCompliance endpoint | Flow observation, export selection, sensor health | Compliance decisions |
| Cilium Hubble | Kubernetes L3/L4/L7 flow visibility and service map | Compliance decisions, raw flow retention, graph queries |
| Graph store | The current node/edge projection and traversal queries | The authoritative record of any control or asset |

The graph is a **read-only projection**. Deleting or rebuilding it must not lose
a control, an asset, or an evidence record.

### 4. Traffic selection is policy, not a dashboard toggle

OpenGRC defines selectors from governed context, for example:

- assets tagged `PHI`, `PII`, `PCI`, or `critical`;
- assets with a failing or overdue control;
- risk score above a threshold;
- a named tenant, lab, or environment boundary;
- a time-bounded investigation approved by a control owner.

The endpoint enforces the selector. It defaults to **flow metadata only**. Packet
capture is opt-in, time-bounded, scoped to named assets, and recorded as an
approval and an evidence event. No selector may capture payloads across a tenant
boundary.

### 5. Minimum graph model

Nodes:

- `Asset` (server, workload, database, service, network device)
- `Endpoint` (IP, CIDR, hostname, Kubernetes service)
- `Control`, `Framework`, `Risk`, `Finding`
- `Tenant` or `Lab`

Edges:

- `FLOWS_TO` with `bytes`, `packets`, `first_seen`, `last_seen`, `direction`,
  `port`, `protocol`, `sampling_rate`, `selector_id`, `evidence_id`
- `OWNS`, `CLASSIFIED_AS`, `GOVERNED_BY`, `FAILS`, `MITIGATES`, `DEPENDS_ON`

An unresolved endpoint is stored as `unknown`. The pipeline never guesses an
asset from an IP address without a reviewed mapping.

### 6. Evidence stays summarised

A flow summary can become a `network-flow` evidence record through the existing
connector or streaming contract. OpenGRC stores the aggregate, the selector, the
time window, and a payload hash. Raw flow records and any approved capture stay
in the collector or graph store with their own retention.

### 7. The console reads a projection

The central console queries the graph service through an OpenGRC-authorized API.
It does not connect the browser directly to the broker or graph database. Live
updates use server-sent events or WebSockets from the projection service, with
the same tenant and role checks as the rest of the console.

## Consequences

- The deployment gains at least four services to operate: collector, broker,
  graph store, and projection API. They are optional and profile-gated.
- Identity resolution is the hard part. An IP-to-asset mapping needs a reviewed
  source (OpenMetadata, NetBox, or the endpoint inventory); an unreviewed mapping
  is worse than `unknown`.
- Flow retention and graph size need explicit limits. A busy lab or production
  network can produce more edges than the console can usefully draw.
- Control owners get a reachability view they did not have: a failing control on
  a live path is more urgent than the same control on an isolated host.
- This is not a SIEM, NDR, or packet-analysis product. Alerting and deep packet
  inspection stay in the detection platform.
- A privacy review is required before enabling packet capture. Flow metadata is
  still personal data when it identifies a person's device or behaviour.

## Phased delivery

1. **Flow inventory.** Enable NetFlow/sFlow/IPFIX on the OpenCompliance endpoint,
   collect into OpenSearch or the existing observability store, and show top
   talkers and sensor health.
2. **Asset enrichment.** Join flows to OpenMetadata asset identities and show
   classification and ownership on the topology.
3. **Control overlay.** Add failing controls, risk score, and findings to the
   graph projection. Keep the console read-only.
4. **Interactive graph.** Add a graph explorer (Memgraph Lab, Neo4j Bloom, or a
   React + Cytoscape/G6 view) with saved investigations.
5. **Policy-driven selection.** Let an approved selector request a bounded
   capture, record the approval, and attach the result to a finding.

## Alternatives considered

- **Put the broker and graph inside OpenGRC.** Rejected: it couples the GRC
  request path to telemetry volume and repeats the boundary ADR 0054 already
  rejected.
- **Use OpenMetadata as the graph.** Rejected: it is a metadata catalog, not a
  real-time flow store, and it must not become the control authority.
- **Use only Grafana.** A good first step for operations, but its node graph is
  not an investigation surface for control owners. Keep it as the phase-1 view.
- **Neo4j Bloom versus Memgraph Lab.** Both are viable. Memgraph is the stronger
  streaming fit; Neo4j is the more familiar operational choice. Decide after a
  spike, not in this ADR.
- **Capture packets by default.** Rejected: privacy, storage, and scope. Flow
  metadata first; capture only by approved selector.

## References

- ADR 0009 - continuous control monitoring and vendor-neutral evidence
- ADR 0020 - OpenMetadata as a read-only catalog source
- ADR 0054 - streaming evidence is a push contract, not a broker inside OpenGRC
- `docs/integrations/network-flow-telemetry.md` - pipeline and selector design
- OpenMetadata knowledge graph documentation
