# deps.dev and OpenSSF Scorecard enrichment

OpenGRC can enrich SBOM components with a license and an OpenSSF Scorecard from
[deps.dev](https://deps.dev). The enrichment is optional; the SBOM and OSV scan
work without it.

See ADR 0036 for the boundary. The score is context, not a compliance verdict.

## Configure

```dotenv
DEPS_DEV_URL=https://api.deps.dev
DEPS_DEV_TIMEOUT=20
```

No token is required. Only public packages are covered. Composer/Packagist is not
supported by deps.dev, so a PHP SBOM will show unsupported components.

## Run

```bash
# Report what would be queried, without calling deps.dev
php artisan opengrc:sbom:enrich 7 --dry-run

# Enrich the components in an imported SBOM
php artisan opengrc:sbom:enrich 7
```

The command updates:

| Field | Source |
|---|---|
| `license` | deps.dev licenses, only when the SBOM left it blank |
| `deps_dev_system` | The deps.dev system (NPM, PYPI, MAVEN, NUGET, CARGO, GO, RUBYGEMS) |
| `scorecard_score` | Project `scorecard.overallScore` |
| `scorecard_date` | Project `scorecard.date` |
| `deps_dev_checked_at` | The query time |

The component's `purl` and version are the query key. A missing project, a private
package or an unsupported system is skipped.

## Score meaning

OpenSSF Scorecard is a 0–10 weighted average of project practices such as branch
protection, code review, signed releases, dependency updates and security policy.
It is not a vulnerability score and not a control assessment.

- A high score is useful context for a supplier review.
- A low score is a prompt to look closer, not an automatic finding.
- A missing score is normal for private or unsupported packages.
- The score changes over time; `scorecard_date` and `deps_dev_checked_at` show
  when it was read.

## Licensing and terms

- deps.dev returns SPDX license expressions where available. The SBOM's stated
  license is not overwritten.
- License data is not legal advice; confirm obligations for your use.
- deps.dev data is queried through its API, not bundled into the repository.
- The API and data terms are part of the integration review.

## Limits

- Public packages only; private/internal packages and Composer/Packagist are not
  covered.
- One or two requests per component; run it as a separate job.
- No full Scorecard check list is stored; the overall score and date are enough
  for the register.
- A failed lookup records an error and continues; it does not block the SBOM or
  the vulnerability scan.
- The enrichment does not create findings or change a control state.
