# Observable enrichment boundary (Cortex and TheHive)

OpenGRC does not run analyzers, store observables or act as a case manager. Cortex
and TheHive remain separate deployments. This page records the boundary so a later
feature does not quietly create an outbound data-sharing path.

See ADR 0037.

## Division of work

| System | Owns |
|---|---|
| TheHive | Cases, tasks, observables, analyst timeline |
| Cortex | Analyzers and responders for an observable |
| OpenGRC | Control, risk, finding and evidence relationship; a reference to the report |

## What OpenGRC may hold

- The analyzer name and version.
- The taxonomy level (for example `info`, `safe`, `caution`).
- A report id or hash, a timestamp and a link.
- The finding or incident the enrichment was requested for.
- Who requested it and why.

It does not hold raw analyzer output, file content, request/response bodies,
credentials, or personal data returned by a third party.

## Data sharing rules

1. Enrichment is human-initiated. Nothing is submitted on import, on finding
   creation or on a schedule.
2. The observable type and the analyzer must be on an approved list. Local
   analyzers (YARA, ClamAV, MISP, internal reputation) are the default.
3. A third-party analyzer needs a privacy and security approval that covers the
   observable type, not only file uploads. A hash or an IP can reveal an
   investigation, a customer or a compromised host.
4. Files and samples are not sent by default. A file submission needs an explicit
   decision for that case.
5. No automatic active response. A responder that blocks, isolates or deletes is a
   SOC decision outside OpenGRC.

## Licensing

Cortex is AGPL-3.0. OpenGRC is AGPL-3.0-only. They stay separate services; no
Cortex code is linked or vendored into this repository. The network clause applies
to the Cortex deployment, not to OpenGRC.

## If a feature is built later

It needs, at minimum:

- an ADR update and a privacy review;
- an analyzer allowlist by observable type;
- a permission for "request enrichment", separate from read access;
- an audit event for every request;
- a retention rule for the reference and the report link;
- a clear statement in the UI that the observable leaves the deployment.

Until then, record the link in the finding or incident notes by hand.
