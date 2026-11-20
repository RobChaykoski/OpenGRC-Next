# 0036 — deps.dev and Scorecard enrich components; they do not decide compliance

Status: accepted
Date: 2026-11-20

## Context

An SBOM says which components are present. It may have a license, but often the
license is missing or expressed inconsistently. A reviewer also wants context:
is the project maintained, does it have a security policy, does it use signed
releases? OpenSSF Scorecard summarises those practices; deps.dev exposes the
Scorecard and package licenses through a read-only API.

That context is useful, but it is not a control verdict. A package with a high
Scorecard can still be vulnerable; a package with a low score can be perfectly
appropriate. The score is one input.

deps.dev covers public packages in Go, RubyGems, npm, Cargo, Maven, PyPI and
NuGet. It does not cover Composer/Packagist, so a PHP SBOM will have unsupported
components.

## Decision

1. **Enrichment is optional.** The SBOM import and OSV scan work without it. The
   enrichment command adds context when a deployment wants it.
2. **Read-only GET requests.** deps.dev needs no token. OpenGRC reads package
   version metadata and project metadata through the read-only guard.
3. **License is filled only when missing.** The SBOM's license is the primary
   value; deps.dev fills a blank field and never overwrites a stated license.
4. **Scorecard is stored with its date.** The overall score, scorecard date,
   deps.dev system and check time are recorded. The checks themselves are not
   copied.
5. **No source, no private packages.** Only package coordinates and public
   metadata are requested. An unsupported or private package is skipped; a 404 is
   not an SBOM failure.
6. **The score is context, not compliance.** It does not change a finding or a
   control state. A low score can inform a risk decision; a high score does not
   replace one.
7. **Composer/Packagist is unsupported.** Do not guess a mapping. The component
   remains in the inventory with no deps.dev enrichment.
8. **Terms and attribution apply.** deps.dev data is queried through its API, not
   bundled into the repository. The API terms are part of the integration review.

## Consequences

- The software inventory can show license and project-health context next to the
  vulnerability count.
- A missing score is normal for private or unsupported packages. The inventory
  shows a dash, not a zero.
- The enrichment is point-in-time. A later Scorecard is a new query, not a live
  feed; `deps_dev_checked_at` is part of the record.
- The command is slower than the import because it makes one or two requests per
  component. Run it as a separate job, not on every page load.
- deps.dev is a third-party service. A failed enrichment leaves the component
  usable and records the failure; it does not block the scan.

## Alternatives considered

- **Treat Scorecard as a compliance score.** Rejected: it measures project
  practices, not the organisation's control environment.
- **Require enrichment for every SBOM.** Rejected: Composer and private packages
  would fail, and the vulnerability scan must work without a third-party service.
- **Scrape the project website for a score.** Rejected: fragile, unversioned and
  outside the API terms.
- **Ignore licenses.** Rejected: license obligations are a real supply-chain
  control; the SBOM and deps.dev together provide useful coverage.
- **Store the full Scorecard checks.** Rejected: the overall score and date are
  enough for the register; the checks stay upstream.
