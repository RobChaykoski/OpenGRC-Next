# 0020 — OpenMetadata is a read-only metadata source, not a second console

Status: accepted
Date: 2026-11-20

## Context

DSPM and data-governance controls need more than scan results. A finding says
that a file contains personal data; a catalog says which dataset it belongs to,
who owns it, how it is classified, and what depends on it. OpenDiscoveryEngine
scans content, and OpenDLP enforces movement policy, but neither owns the data
inventory.

OpenMetadata is an Apache-2.0 data catalog. It already holds database, schema,
table, column, dashboard, pipeline and ML-model metadata, plus owners, tags,
domains, glossaries, lineage and data-quality results. It has its own UI, its own
ingestion runners, and a large connector library.

The obvious mistakes are symmetrical. Folding a catalog into OpenGRC would make
the GRC release depend on a Java/Elasticsearch stack and duplicate a product that
is better at cataloguing. Mirroring the whole catalog into OpenGRC would create a
second, stale catalog with its own search, permissions and drift. Neither answers
the compliance question any better.

## Decision

1. **OpenMetadata remains the catalog and the catalog UI.** OpenGRC is the
   compliance console: it holds the control, the evidence, the risk and the audit
   record. Operators curate metadata in OpenMetadata; they prove a control in
   OpenGRC.
2. **Integration is read-only HTTP metadata.** No OpenMetadata code is copied,
   vendored or linked into OpenGRC. It is a separately deployed service, on the
   same boundary as OpenDiscoveryEngine (ADR 0018) and ClamAV. OpenGRC remains
   AGPL-3.0-only; OpenMetadata remains Apache-2.0.
3. **Metadata only.** OpenGRC requests table, column, owner, tag, service and
   domain metadata. It never requests sample data, profiler values, table rows,
   query text or raw content, and it never enables a profiling field to make a
   dashboard look richer. Catalog metadata can itself contain personal data, so
   it is treated as confidential and is never copied into logs.
4. **Phase 1 is aggregate evidence, not a catalog mirror.** One evidence record
   per catalog service carries counts: tables, columns, owned tables, classified
   tables, coverage percentages, classification tag counts and mapped
   classifications. No table FQNs, lineage graph or per-column mirror is stored in
   OpenGRC in this slice.
5. **Classification is curated, not guessed.** A table counts as classified when
   the table or any column carries a tag in a configured classification namespace
   (default `PII`). A configured map turns a full tag FQN into an OpenGRC
   data-classification code. Unknown tags are counted by FQN and left unmapped.
6. **Authentication is a read-only bot token.** The token is a secret stored in the
   encrypted source configuration, never rendered to the browser, never logged,
   and rotated on a schedule. Every request is GET/HEAD through the read-only HTTP
   guard; there is no write client.
7. **Failure is visible.** An unreachable catalog, a rejected token, a count
   mismatch or a truncated pagination run fails the collection. An empty catalog
   is Unknown, never Effective. A stale measurement stays stale in the evidence
   browser and in `opengrc:doctor`; it is never presented as a clean estate.
8. **One catalog service per source.** Multiple catalogs or tenants are separate
   sources, not an implicit merge. Multi-tenant behaviour is a later decision.
9. **No write-back in phase 1.** OpenGRC does not create tags, owners or glossary
   terms in the catalog. If write-back is ever added, it needs a review queue and
   its own decision, because a mirrored record and a catalog record are not the
   same object.
10. **The quickstart is not a production deployment.** The local Docker compose
    (Elasticsearch, MySQL/PostgreSQL, server, ingestion/Airflow) is an evaluation
    stack with default credentials and a 6 GiB / 4 vCPU footprint. OpenGRC does
    not add it to its default compose, and no integration guide treats it as
    hardened.

## Consequences

- DSPM gets the context it was missing: dataset ownership and classification
  coverage, not only match counts.
- There is another deployable to run, back up, upgrade and monitor. It must not
  become a hidden dependency of the console; an unavailable catalog produces
  stale evidence, not an outage.
- Evidence quality is bounded by catalog quality. A catalog with no owners
  produces a real Failed finding, which is the point, but it also means the
  connector cannot be "fixed" by changing OpenGRC.
- Classification vocabulary becomes a governance artefact. The namespace and tag
  map need an owner, or two teams will use the same word for different data.
- The bot token is a high-value credential. It is read-only in intent, but the
  OpenMetadata API is broad; scope it, store it as a secret, and rotate it.
- Lineage and data-quality results are deliberately deferred. They are valuable
  for impact analysis and integrity evidence, but each adds API cost and a new
  failure mode. Aggregate governance coverage comes first.

## Alternatives considered

- **Build a catalog into OpenGRC.** Rejected: it duplicates a mature product,
  adds connector and ingestion work that is not GRC-specific, and makes the
  console responsible for metadata freshness it does not control.
- **Mirror the full catalog and lineage graph.** Rejected: a second catalog drifts
  immediately, multiplies the data-protection surface, and produces screens that
  are worse than OpenMetadata's own.
- **Use OpenMetadata as the compliance console.** Rejected: it has no control
  catalogue, evidence ledger, audit trail or risk register, and its users are data
  teams, not auditors.
- **Rely on content scanning alone.** Rejected: a scan can find a match but cannot
  say who owns the dataset, whether it is classified, or what downstream depends
  on it. That is exactly the context a GRC programme needs.
- **Use a proprietary catalog as the only source.** Rejected: it makes the
  customer's data inventory dependent on a subscription, and exports are not
  independently verifiable. OpenMetadata is one source among several, not a
  mandatory dependency.
