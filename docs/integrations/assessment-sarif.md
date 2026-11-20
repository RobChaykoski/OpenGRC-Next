# SARIF assessment import

OpenGRC imports a SARIF 2.1 report and creates findings. It does not run the
scanner. Checkov, ScubaGear, OSV-Scanner, ZAP, Semgrep and similar tools stay in
the CI or security pipeline; the report is the hand-off.

See ADR 0035 for the boundary.

## What it imports

- The tool name and version.
- The report SHA-256 and import time.
- Each result's rule id, message, level, severity property, path and line.
- An application or asset link, if supplied.

It does **not** import code snippets, source archives, request/response bodies,
credentials or the raw report.

## Command

```bash
# Parse and report without writing
php artisan opengrc:assessment:import storage/app/checkov.sarif --dry-run

# Import and link to an application or asset
php artisan opengrc:assessment:import storage/app/checkov.sarif --application=12 --asset=42
```

The command prints the report reference and finding count.

## Finding identity

A finding is keyed by:

```
sarif:{tool}:{ruleId}:{path}:{line}
```

Re-importing the same result updates the finding, increments `occurrences` and
refreshes `last_seen_at`. A resolved finding reopens if the result is still
present; an accepted risk stays accepted.

## Severity and due dates

1. `properties.severity` from the result, if present.
2. Otherwise SARIF `level`: `error` → High, `warning` → Medium, `note` → Low.
3. A numeric or textual severity only maps to critical/high/medium/low; anything
   unknown is Medium.

Due dates: critical 7 days, high 30, medium 60, low 90 from first sight.

## Tools

| Tool | Output | Note |
|---|---|---|
| Checkov | SARIF or JSON | Use SARIF; the native JSON needs a converter |
| ScubaGear | JSON/SARIF depending on version | Use SARIF where available |
| OSV-Scanner | SARIF or JSON | Use SARIF; SBOM support is separate |
| ZAP | SARIF or JSON | Use SARIF |
| Semgrep | SARIF | Native SARIF |

A tool without SARIF needs a converter or a separate importer. Do not add a new
connector per scanner.

## Limits

- SARIF 2.x only; 1.x is rejected.
- No source snippets, request/response content or secrets.
- No auto-close when a result disappears.
- No suppression/baseline policy inside OpenGRC; baseline in the scanner.
- No scanner execution, scheduling or credentials.
- The report file stays outside OpenGRC; the register keeps the hash and metadata.

## Evidence and privacy

- A SARIF path can reveal internal repository and service names. Treat the report
  and findings as internal.
- The message may contain a secret if a tool is misconfigured. Review before
  importing; do not paste a raw report into a ticket or an AI prompt.
- The report hash is the link to the original; keep the file in the pipeline or
  document store under the same retention as the finding.
