# 0028 — SBOM and OSV are supply-chain evidence, not a scanner

Status: accepted
Date: 2026-11-20

## Context

Software supply-chain risk is now a control-testing problem, not only a build
problem. A team needs to answer: what components are in this application, which
versions, which licenses, and which are affected by a known advisory?

Three kinds of tool are easy to confuse:

- **SBOM generators** describe the components in an artifact.
- **Scanners** (OSV-Scanner, Checkov, ScubaGear, Trivy) assess an artifact or a
  configuration and produce findings.
- **Advisory sources** (OSV.dev, deps.dev, OpenSSF Scorecard) provide data about
  packages, vulnerabilities and project health.

OpenGRC should not become a scanner. It should accept an SBOM, query an advisory
source, store the evidence and turn a vulnerable component into a finding with an
owner and a date. The source code, container image and build environment stay in
the engineering pipeline.

OSV's batch query is a `POST`, but it is a read-only query. That requires a
narrow transport exception, not a general write client.

## Decision

1. **Import the SBOM; do not scan the source.** Phase 1 accepts CycloneDX JSON.
   The document's SHA-256, format, spec version and component count are the
   evidence. Source files, container layers and build logs are not imported.
2. **Store package coordinates, not package content.** A component is a purl,
   name, version, supplier and license. No source archive, binary or environment
   variable is stored.
3. **Query OSV in batches; read advisories by GET.** `POST /v1/querybatch` is the
   only permitted query POST, through the `grcReadQuery` guard. Advisory details
   are read with `GET /v1/vulns/{id}`. The path comes from service code, never
   from source configuration.
4. **A vulnerability becomes a finding.** The finding has a stable dedupe key of
   OSV id plus component purl, a severity, a due date and a link back to the
   advisory record. A re-scan updates the same finding; a resolved finding
   reopens if the vulnerable component is still present; an accepted risk stays
   accepted.
5. **The advisory is external and point-in-time.** Store the OSV id, aliases,
   summary, severity, fixed version, source URL, published and modified dates,
   and the scan time. Do not present a snapshot as a live feed.
6. **License and project-health data are optional enrichment.** deps.dev and
   OpenSSF Scorecard can add license and scorecard context later. They are not a
   dependency of the first scan, and they do not change the control verdict.
7. **No scanner execution.** Checkov, ScubaGear, OSV-Scanner and similar tools
   run in the engineering pipeline. Their JSON/SARIF output can be imported by a
   later generic assessment path; OpenGRC does not run them.
8. **An SBOM is confidential.** It reveals the stack, versions and sometimes
   internal package names. It is not published in the trust center and is
   treated like internal evidence.
9. **A vulnerable component is a finding, not an automatic control failure.**
   Exploitability, exposure, compensating controls and the fixed version decide
   the response. The control verdict still comes from a validation rule or a
   person.

## Consequences

- The GRC record can answer "which applications contain a vulnerable component,
  who owns the fix, and when is it due?" without copying an SBOM into a
  spreadsheet.
- The register is only as good as the SBOM. A stale or incomplete SBOM produces
  false comfort; the document timestamp and source are part of the record.
- OSV queries send package coordinates to a public API. That is normally public
  information, but internal package names can still be sensitive; review the
  SBOM before importing it.
- Severity and fixed-version data vary by advisory source. The finding records
  what OSV said at scan time; it does not invent a score.
- The first slice has no UI. Findings appear in the existing queue; an inventory
  view is a later addition.
- deps.dev and Scorecard are not bundled. Their terms and attribution are a
  separate integration decision.

## Alternatives considered

- **Run a scanner inside OpenGRC.** Rejected: execution risk, a duplicate of the
  engineering pipeline, and a large runtime surface.
- **Query OSV per component.** Rejected: slow, rate-limit sensitive and wasteful.
  Batch first, then fetch only the advisory details that were returned.
- **Store the SBOM body and source.** Rejected: the document is the evidence, not
  the source tree; storing source expands the privacy and backup surface.
- **Create a new vulnerability model with no finding.** Rejected: a vulnerability
  without an owner, a due date and a status is a report, not a remediation
  record.
- **Treat an SBOM as proof of compliance.** Rejected: an SBOM says what is in the
  build, not whether the control is effective.
- **Add deps.dev and Scorecard to the first slice.** Rejected: they are useful
  but not required to prove the import-and-find loop. Add them after the base
  pipeline is trustworthy.
