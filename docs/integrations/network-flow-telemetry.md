# Network-flow telemetry and the risk graph

This page is the implementation design behind ADR 0058. It describes how the
OpenCompliance endpoint, OpenMetadata, OpenGRC, and a graph projection fit
together. It is a design, not a shipped feature.

## Goal

Show the live network paths that matter to a control owner:

- which assets talk to which assets;
- which paths cross a trust boundary or a tenant boundary;
- which flows touch an asset with a failing control, a high risk score, or a
  sensitive classification;
- which traffic should be selected for deeper analysis in the central console.

## Non-goals

- Replacing the SIEM, NDR, or packet-analysis platform.
- Capturing payloads by default.
- Making OpenGRC a flow database, broker, or graph store.
- Writing control status back into OpenMetadata.
- Guessing an asset from an IP address without a reviewed mapping.

## Layers

| Layer | Owner | Responsibility |
|---|---|---|
| Collection and selection | OpenCompliance endpoint | Enable NetFlow/sFlow/IPFIX, observe eBPF or cloud flows, apply selectors, export metadata |
| Transport | Broker | Durable, replayable flow and change events |
| Enrichment | Stream processor | Join flows to OpenMetadata assets and OpenGRC control/risk state; compute windows |
| Projection | Graph store | Current node/edge view and traversal queries |
| Presentation | OpenGRC console | Authorized, read-only investigation surface |
| Governance | OpenGRC | Controls, risks, findings, approvals, evidence records |
| Metadata | OpenMetadata | Asset identity, ownership, lineage, classification |

## Pipeline

```text
sensor / endpoint
  -> OpenCompliance collector
  -> broker topic: flows.raw
  -> enricher
       OpenMetadata asset lookup
       OpenGRC control and risk lookup
       asset/tenant resolution
  -> broker topics: flows.enriched, graph.changes
  -> graph writer
  -> graph store
  -> projection API
  -> OpenGRC console
```

### Flow record (minimum)

```json
{
  "tenant": "acme",
  "sensor": "edge-01",
  "src": "10.20.4.15",
  "dst": "10.20.9.40",
  "src_port": 51422,
  "dst_port": 5432,
  "protocol": "tcp",
  "direction": "internal",
  "bytes": 184320,
  "packets": 412,
  "first_seen": "2026-11-23T14:02:11Z",
  "last_seen": "2026-11-23T14:03:41Z",
  "sampling_rate": 100,
  "selector_id": "phi-boundary"
}
```

The record is metadata. It does not contain payloads, credentials, query text, or
file names.

### Enriched edge (minimum)

```json
{
  "src_asset": "billing-api",
  "dst_asset": "patient-db",
  "edge": "FLOWS_TO",
  "bytes": 184320,
  "packets": 412,
  "first_seen": "2026-11-23T14:02:11Z",
  "last_seen": "2026-11-23T14:03:41Z",
  "dst_classification": ["PHI"],
  "controls": ["SC-7", "AC-4"],
  "control_status": "failing",
  "risk_score": 16,
  "selector_id": "phi-boundary",
  "evidence_id": "flow-summary-2026-11-23T14:05Z"
}
```

## Traffic selection

Selection is a governed policy, not a per-user dashboard filter. The endpoint
receives a selector, enforces it, and reports what it exported.

```yaml
selectors:
  - id: phi-boundary
    description: Flow metadata for assets tagged PHI or PII with a failing control.
    match:
      any:
        - asset_tag: PHI
        - asset_tag: PII
      all:
        - control_status: failing
    export:
      flow_metadata: true
      packet_capture: false
    window: 15m
    tenant: acme
    approved_by: control-owner@example.com
    expires_at: "2026-11-30T00:00:00Z"
```

Rules:

- Flow metadata is the default. Packet capture is a separate, approved action.
- A selector names its tenant, owner, and expiry.
- A selector that matches a tenant boundary is rejected.
- Every selector change is an auditable event.
- The endpoint reports exported bytes and record counts per selector.
- A selector that produces no data is `unknown`, not `compliant`.

## Graph model

```text
(Asset)-[:OWNS]->(Team)
(Asset)-[:CLASSIFIED_AS]->(Classification)
(Asset)-[:GOVERNED_BY]->(Control)
(Control)-[:MITIGATES]->(Risk)
(Control)-[:FAILS]->(Finding)
(Asset)-[:FLOWS_TO {bytes, packets, port, protocol, last_seen}]->(Asset)
```

Keep the live `FLOWS_TO` edge as a rolling window. Historical summaries belong in
the evidence record, not in an ever-growing edge list.

## Console experience

The first useful view is a table and a topology, not a physics graph:

1. **Top talkers** by bytes and by connection count.
2. **Risky paths**: a flow touching a sensitive asset or a failing control.
3. **Boundary crossings**: internal to external, tenant to tenant, or a zone
   change.
4. **Sensor health**: last flow, sampling rate, export errors.
5. **Investigation**: select an asset, show its neighbours, open the linked
   control and finding, request a bounded capture.

The graph explorer is an enhancement. A table, filters, and a simple node-link
view answer most control-owner questions sooner.

## Deployment shape

Run the telemetry services as a separate compose profile or stack:

```text
opengrc-web / opengrc-worker      existing app
opencompliance-endpoint           collection and selection
flow-collector                    NetFlow/sFlow/IPFIX receiver
broker                            Kafka, Redpanda, or NATS JetStream
enricher                          stream processor
graph                             Memgraph or Neo4j
projection-api                    read-only API for the console
```

Do not mount the Docker socket into the endpoint. Do not put broker or graph
credentials in the OpenGRC web container. The projection API is the only service
the console calls.

## Retention and capacity

| Data | Suggested default | Notes |
|---|---|---|
| Raw flow records | 7 days | Enough for investigation; not an archive |
| Enriched graph edges | 24 hours rolling | Rebuildable from the broker |
| Flow summaries | 1 year | Stored as OpenGRC evidence metadata |
| Packet captures | 24 hours, approved only | Scoped to an investigation |
| Selector audit events | 1 year | Who selected what, when, and why |

Set per-tenant caps. A noisy lab network must not starve production.

Arkime is a full-packet store, not a flow source. Keep the PCAP in Arkime and
import only session counts plus retention and sensor health into OpenGRC; a
bounded capture is an investigation artifact under ADR 0019, not a GRC record.
The same rule applies to any other PCAP store the deployment uses.

## Privacy and security

- Metadata only by default; no payload, credentials, or query text.
- Tenant and role checks on every projection query.
- Capture requires an approved selector with an owner and an expiry.
- Retention is explicit and enforced by the collector.
- The endpoint is opt-in and never sends telemetry to an external service.
- Flow data can still identify a person's device or behaviour; treat it as
  personal information and include it in the privacy review.

## Phased plan

1. **Inventory.** Enable exports, collect flows, show top talkers and sensor
   health. Keep the data in the existing observability store.
2. **Enrich.** Join to OpenMetadata assets and show ownership and classification.
3. **Overlay.** Add failing controls and risk scores to the read-only projection.
4. **Explore.** Add the graph explorer and saved investigations.
5. **Select.** Add approved, time-bounded capture and attach the result to a
   finding.

## Open questions

- Which flow exporter does the first supported endpoint ship: goflow2, ntopng,
  or a native collector?
- Is the first broker Kafka/Redpanda, or NATS JetStream for a smaller footprint?
- Which graph store wins the spike: Memgraph for streaming or Neo4j for
  familiarity?
- Does the first release need packet capture, or is flow metadata plus a link to
  the existing PCAP store enough?
- Which identity source is authoritative for IP-to-asset mapping in a deployment
  without NetBox?
