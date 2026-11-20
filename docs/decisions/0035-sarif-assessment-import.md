# 0035 — Scanner output is findings evidence, not a scanner

Status: accepted
Date: 2026-11-20

## Context

Checkov, ScubaGear, OSV-Scanner, ZAP, Semgrep and similar tools produce findings
about infrastructure, cloud configuration, dependencies and applications. They
run in CI, on a workstation or in a dedicated security pipeline. OpenGRC should
not run them again.

SARIF 2.1 is the common output format. It carries a tool, a rule id, a level, a
message and a location. It can also carry code snippets and other context that
must not enter the GRC database.

## Decision

1. **Import SARIF; do not run the scanner.** OpenGRC parses a SARIF 2.1 report,
   records the report hash and tool metadata, and creates findings.
2. **One report, deduplicated findings.** A finding is keyed by tool, rule id and
   location. Re-importing the same report updates the finding and increments
   occurrences instead of duplicating it.
3. **No source code or snippets.** The importer reads the rule id, message, level,
   properties, path and line. It never reads a `snippet`, a source archive or a
   request/response body.
4. **Severity is explicit.** A tool-provided `properties.severity` wins; otherwise
   SARIF `level` maps to High, Medium or Low. The finding records what the tool
   said.
5. **The report is linked to an application or asset.** A finding links back to
   the report that produced it, so the evidence is inspectable.
6. **No auto-close.** A result disappearing from a later report does not close the
   finding; a person verifies the fix and resolves it. Accepted findings stay
   accepted.
7. **A scanner result is not a control verdict.** Exploitability, exposure,
   compensating controls and the control owner decide the response. Validation
   rules or a person set the control state.
8. **No per-tool connectors.** A tool that cannot emit SARIF needs a small
   converter or a separate importer, not a new console integration.

## Consequences

- One importer covers several tools and keeps the finding queue consistent.
- The report hash and tool version make the evidence reproducible; the file stays
  in the pipeline or document store.
- Paths and rule ids may reveal internal structure. The report and findings are
  internal evidence; do not publish them in the trust center.
- Deduplication by location means a moved file can create a new finding. That is
  visible in the occurrence history and preferable to silently merging unrelated
  results.
- A scanner that reports thousands of low-value results will flood the queue.
  Filter or baseline in the tool before importing; OpenGRC does not invent a
  suppression policy.

## Alternatives considered

- **Add a Checkov/ScubaGear connector per tool.** Rejected: each tool has its own
  JSON shape, version and auth; SARIF is the shared contract.
- **Run the scanners in OpenGRC.** Rejected: execution risk, duplicate pipeline
  and a large runtime surface.
- **Store the raw report as the evidence.** Rejected: it can contain source
  snippets and secrets; the hash, metadata and findings are enough.
- **Import every result as a new finding.** Rejected: a weekly scan would create
  duplicate noise and hide the trend.
- **Auto-close a finding when it disappears.** Rejected: a scanner miss is not a
  verified fix.
