# SBOM and OSV supply-chain evidence

OpenGRC imports a CycloneDX SBOM, stores its components, queries OSV.dev for
known vulnerabilities and turns a vulnerable component into a finding with an
owner and a due date. It does not scan source code, container images or build
environments. Those belong in the engineering pipeline.

See ADR 0028 for the boundary.

## What it does

1. Imports a CycloneDX JSON SBOM.
2. Stores the document hash, format, spec version and component coordinates.
3. Queries OSV in batches by purl ecosystem and version.
4. Reads advisory details by OSV id.
5. Creates or updates a Finding per vulnerable component and OSV id.
6. Stores the advisory and the scan time as evidence.

## Commands

```bash
# Parse and report without writing or calling OSV
php artisan opengrc:sbom:import storage/app/sbom.json --dry-run

# Import and link to an application or asset
php artisan opengrc:sbom:import storage/app/sbom.json --application=12 --asset=42

# Import and query OSV in one step
php artisan opengrc:sbom:import storage/app/sbom.json --application=12 --scan

# Re-scan an imported document later
php artisan opengrc:sbom:scan 7

# Report what a scan would query, without calling OSV
php artisan opengrc:sbom:scan 7 --dry-run
```

`--application` and `--asset` are optional OpenGRC ids. A document can be
imported without them; the finding then has no asset link until one is added.

## Supported purl ecosystems

| purl type | OSV ecosystem | Query name |
|---|---|---|
| `pkg:composer/...` | Packagist | `vendor/package` |
| `pkg:npm/...` | npm | `@scope/name` or `name` |
| `pkg:pypi/...` | PyPI | `name` |
| `pkg:maven/...` | Maven | `group:artifact` |
| `pkg:nuget/...` | NuGet | `name` |
| `pkg:gem/...` | RubyGems | `name` |
| `pkg:golang/...` | Go | `module/path` |
| `pkg:cargo/...` | crates.io | `name` |
| `pkg:hex/...` | Hex | `name` |
| `pkg:pub/...` | Pub | `name` |
| `pkg:swift/...` | SwiftURL | `name` |
| `pkg:githubactions/...` | GitHub Actions | `owner/action` |

An unsupported purl type or a component without a version is skipped and counted
as unsupported. The importer does not guess an ecosystem.

## Configuration

```dotenv
OSV_API_URL=https://api.osv.dev
OSV_TIMEOUT=20
OSV_BATCH_SIZE=500
```

No OSV token is required. The batch query is the only `POST`; advisory reads are
`GET`. The transport guard refuses any other method or path, disables redirects
and keeps TLS verification on.

## Data model

| Record | What it keeps |
|---|---|
| `sbom_documents` | Name, format, spec version, SHA-256, source path, component count, application/asset link, import time |
| `software_components` | purl, name, version, supplier, license, ecosystem, scope, type |
| `software_vulnerabilities` | OSV id, aliases, summary, details, severity, affected packages, fixed version, source URL, published/modified, first/last seen |
| `findings` | Owner, status, severity, due date, dedupe key and resolution |

A component is unique within a document by purl, or by `name@version` when the
purl is missing. A re-import of the same document hash for the same application
and asset updates the components instead of creating a second inventory.

## Findings

- Dedupe key: `osv:<OSV id>:<component key>`.
- Title: `OSV <id> in <component>@<version>`.
- Severity: OSV `database_specific.severity`, then ecosystem severity, then a
  numeric CVSS score, then Medium.
- Due date: critical 7 days, high 30, medium 60, low 90 from first sight.
- A re-scan increments occurrences and updates `last_seen_at`.
- A resolved finding reopens if the vulnerable component is still present.
- An accepted-risk finding stays accepted; the advisory record is still updated.
- No auto-resolution: an advisory disappearing from a scan does not close a
  finding. A person verifies the upgrade and resolves it.

## Limits

- CycloneDX JSON only in this slice. SPDX and CycloneDX XML are later.
- No source, container or runtime scan; import a scanner's SBOM or report.
- No deps.dev or OpenSSF Scorecard enrichment yet.
- No automatic upgrade or pull request.
- No per-application inventory UI yet; findings appear in the existing queue and
  the raw records are queryable.
- The SBOM is a snapshot. A stale document is visible by its import time, not by
  a live "clean" status.

## Privacy and licensing

- An SBOM reveals the stack, versions and sometimes internal package names. Treat
  it as confidential; do not publish it in the trust center.
- Only package coordinates are sent to OSV. Do not include source archives,
  credentials or build secrets in the SBOM.
- OSV data is external and point-in-time. Record the advisory URL and scan time;
  do not present the finding as a live feed.
- OSV terms and attribution apply. The data is queried through the API, not
  bundled into the repository.

## Related

- `docs/decisions/0017-connector-authentication-requests.md` — the narrow POST
  exception pattern.
- `docs/decisions/0028-sbom-and-osv-supply-chain-evidence.md` — the decision.
- `docs/grc/evidence-standard.md` — evidence types and the ledger.
