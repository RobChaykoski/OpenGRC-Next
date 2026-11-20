# 0037 — Observable enrichment is opt-in and privacy-gated

Status: accepted
Date: 2026-11-20

## Context

Cortex (TheHive Project) is an AGPL-3.0 observable analysis and active response
engine. It runs analyzers and responders against IPs, domains, URLs, hashes,
emails and files. Some analyzers call third-party services; some can submit a file
or a hash to a public reputation service. TheHive is the case manager that usually
sits in front of it.

OpenGRC is not a SOC case manager and does not need to become one. Its useful
relationship to Cortex is narrow: an analyst reviewing a finding or an incident
may want enrichment for a specific observable, and the GRC record may need a
reference to that report.

Sending an observable to a third party is a data-sharing decision. A hash or an IP
can reveal the existence of an investigation, a customer, or a compromised host.

## Decision

1. **No Cortex runtime in OpenGRC.** OpenGRC does not run analyzers, store
   observable content or become a case-management UI. Cortex and TheHive remain
   separate deployments.
2. **Enrichment is opt-in and human-initiated.** No automatic submission on import
   or on finding creation. An analyst chooses the observable, the analyzer and the
   purpose.
3. **Data-sharing is decided before the request.** A third-party analyzer is
   allowed only for the observable types and services approved by privacy and
   security. Local analyzers (YARA, ClamAV, MISP, internal reputation) are the
   default. Files and samples are not sent by default.
4. **The GRC record holds a reference, not the report.** Store the analyzer name,
   version, taxonomy level, report id/hash, timestamp and a link. Do not copy raw
   request/response content, file content or personal data into OpenGRC.
5. **AGPL is a separate-service boundary.** Cortex is not linked or vendored into
   OpenGRC. Its network clause applies to its own deployment; OpenGRC remains
   AGPL-3.0-only.
6. **No connector in the first slice.** This decision sets the boundary for a
   later, explicit action. A generic "enrich this observable" feature needs a
   privacy review, an analyzer allowlist and an audit event.
7. **The case manager stays the case manager.** Incident timelines, tasks and
   observables belong in TheHive or the SOC platform. OpenGRC holds the control,
   risk and evidence relationship.

## Consequences

- OpenGRC avoids a new personal-data pool and a new outbound data-sharing path.
- Analysts keep using Cortex/TheHive for triage; the GRC record can link to the
  report without duplicating it.
- A future enrichment feature is slower to build because it needs a data-sharing
  decision and an allowlist. That is the correct order.
- A hash submitted to a public service can still reveal an investigation; the
  approval must cover that, not only file uploads.
- Cortex's AGPL obligations stay with its deployment. No code enters the
  repository.

## Alternatives considered

- **Build Cortex into OpenGRC.** Rejected: AGPL code coupling, a new runtime, a
  second case manager and a large privacy surface.
- **Auto-enrich every observable.** Rejected: it leaks investigation context to
  third parties without a per-case decision.
- **Store the full analyzer report.** Rejected: raw content, personal data and
  third-party terms; the reference and summary are enough.
- **Use Cortex as the compliance console.** Rejected: no controls, evidence ledger,
  risk register or audit trail.
