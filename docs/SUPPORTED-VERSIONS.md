# Supported Versions and Upgrade Policy

This document is the contract between the OpenGRC codebase and the environment it
runs in. It exists so that "it works on my machine" stops being an upgrade
strategy.

`versions.yaml` is the machine-readable baseline. This page explains the policy
behind it and what to do when a component moves.

## What "supported" means

A version is supported when all three are true:

1. It is a stable, generally available release (no alpha, beta, RC, or nightly).
2. It is still maintained upstream, or is deliberately frozen with a documented
   reason.
3. It is covered by this repository's test suite and a manual smoke test of login,
   navigation, and one write operation.

We do **not** mean "the newest possible version of everything". The target is the
newest release that satisfies the three conditions together.

## Supported baseline

| Tier | Component | Floor | Tested against | Notes |
|---|---|---|---|---|
| Runtime | PHP | 8.4 | 8.4 | 8.3 and below are unsupported; Laravel 13 requires 8.4+ |
| Runtime | Composer | 2.5 | 2.11 | |
| Runtime | Node.js | 20.19 | 24 LTS | Vite 8 requires 20.19+ or 22.12+ |
| Runtime | npm | 9 | 11 | |
| Data | SQLite | 3.35 | bundled with PHP | Default for dev and small installs |
| Data | MySQL / MariaDB | 8.0 / 10.6 | 8.0 | |
| Data | PostgreSQL | 14 | 14+ | |
| Backend | Laravel | 13.x | 13.35 | Locked by `composer.lock` |
| Backend | Filament | 4.x | 4.15 | Filament majors change panel APIs; treat as a platform upgrade |
| Backend | Livewire | 3.x | 3.8 | |
| Backend | nwidart/laravel-modules | 11.x | 11.1 | `Modules/*` merge into Composer autoload |
| Build | Vite | 8.x | 8.3 | |
| Build | Tailwind CSS | 4.x | 4.3 | CSS-first configuration |

Extensions marked required in `versions.yaml` must be present or the application
will fail in ways that look like application bugs. `php artisan opengrc:doctor`
checks this for you.

## Upgrade tiers

Upgrades are grouped by blast radius. Do not mix tiers in one change.

### Tier 1 — patch releases (safe to batch)

Composer/npm patch and minor updates inside an existing major. Routine.

- Update lockfiles, run the test suite, run `opengrc:doctor`, smoke test.
- No migration rewrite, no manual env changes.
- May be automated (see `renovate.json`), with CI as the gate.

### Tier 2 — build tooling

Node, npm, Vite, Tailwind, and the asset pipeline. These break builds, not data.

- Validate `npm ci && npm run build` on the supported Node LTS lines.
- Confirm `public/build/manifest.json` is regenerated and committed by deploy, not
  by hand.
- Roll back by reverting the lockfile and rebuilding; no database impact.

### Tier 3 — backend framework and libraries

Laravel, Filament, Livewire, Passport/Sanctum, nwidart modules.

- One major at a time, in its own branch and PR.
- Read the upstream upgrade guide and list every breaking change in the PR body.
- Require the full suite plus manual smoke test of every installed module.
- Filament major upgrades must also verify panels, widgets, and custom form
  components, because Filament deviations are not fully covered by tests.

### Tier 4 — runtime and data

PHP minor, database engine major, storage backend.

- Requires a restore-tested backup first. A backup you have not restored is a
  hope, not a backup.
- Run `php artisan migrate --pretend` and review the SQL before `migrate`.
- Verify queue workers are stopped during the switch and restarted after deploy.
- Keep the previous runtime installed until the smoke test passes.

## Compatibility gates

A change is only merged when:

- `composer validate --strict` passes.
- The PHPUnit suite passes on the supported PHP floor (8.4) and the next minor.
- `npm ci && npm run build` passes on the supported Node lines.
- `php artisan opengrc:doctor` reports no failures in a fresh clone.
- No new dependency is pre-release, abandoned, or unmaintained. Check the package
  page, not just the version number.

## Dependency rules

- **Commit both lockfiles.** `composer.lock` and `package-lock.json` are the real
  pins. `composer.json` ranges are intent; the lockfile is the build.
- **Deploy with `composer install --no-dev --prefer-dist`, never `composer update`.**
  Updating on deploy means production is a different build every time.
- **`npm ci`, never `npm install`**, in CI and deploys.
- **Prefer the current LTS of a dependency over the newest release.** A library
  that shipped last week has no upgrade history to learn from.
- **Freeze deliberately vulnerable or intentionally old packages only with a written
  exception** in `versions.yaml`. An undocumented old pin is indistinguishable from
  neglect.

## Known compatibility traps

These have all bitten OpenGRC or its sibling projects. They are cheap to check and
expensive to miss.

- **Windows paths in `.env`.** Dotenv treats backslashes inside double quotes as
  escape sequences, so `DB_DATABASE="C:\...\opengrc.sqlite"` fails to parse. Use
  forward slashes. The installer normalises this, and `opengrc:doctor` flags it.
- **NTS vs ZTS PHP builds.** Windows builds are commonly thread-safe (ZTS). Most
  extensions are fine, but SAPIs and some extension DLLs are build-specific. Match
  the build when installing extensions.
- **Missing extensions present silently at install, absent at runtime.** The CLI
  `php.ini` and the web SAPI `php.ini` can differ. Verify the web SAPI too.
- **Node engine drift.** Vite 8 refuses Node versions below 20.19/22.12. A stale
  Node install fails at build time, not at install time.
- **Migration ordering on SQLite.** SQLite rebuilds tables for many ALTERs. Long
  migration chains are slower and less forgiving than on MySQL; test upgrades
  against a copy of a real SQLite file, not only a fresh database.
- **Cached config after env changes.** `config:cache` bakes `.env` values in. Any
  env change in production requires `php artisan optimize:clear` or a re-cache.

## Review cadence

- **Monthly:** dependency dashboard review, patch/minor updates, security advisories.
- **Quarterly:** re-check upstream EOL dates, re-run the baseline table, confirm the
  Node and PHP lines are still supported, and update this document.
- **Yearly:** plan the runtime major (PHP) and framework major (Laravel/Filament).

Record the review date and any new exception in `versions.yaml` so the next person
inherits facts instead of folklore.
