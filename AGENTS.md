# AGENTS.md

Guidance for AI agents and human contributors working in this repository. Read it
before changing code. When this file and the code disagree, fix this file.

## What this project is

OpenGRC is a self-hosted governance, risk and compliance platform: Laravel +
Filament + Livewire, with modules under `Modules/`. It is also becoming a
hands-on training target for GRC work, integrated with OpenCyberRange labs.

Read these before touching infrastructure or dependencies:

- `docs/SUPPORTED-VERSIONS.md` — the supported baseline and upgrade tiers
- `docs/UPGRADING.md` — the upgrade runbook
- `docs/decisions/` — accepted architecture decisions, including the boundary
  with OpenCyberRange
- `docs/grc/` — how the product is meant to be operated as a GRC programme; the
  capability map lists known gaps honestly
- `docs/security/` — container hardening, WAF mode, and what is not hardened yet
- `docs/OSS-POLICY.md` — license, dependency allowlist, contribution model
- `config/framework_catalog.php` — framework provenance and licensing register

## Commands

```bash
composer install                 # dependencies (never `composer update` for a deploy)
npm ci                           # front-end dependencies (never `npm install` in CI)
php artisan opengrc:install      # first-time install (add --unattended for defaults)
php artisan serve                # development server
npm run dev                      # Vite dev server
npm run build                    # production assets
php artisan test                 # PHPUnit/Pest suite
./vendor/bin/pint                # format (the tree is kept clean; CI enforces it)
./vendor/bin/phpstan analyse     # static analysis (baseline in phpstan-baseline.neon)
php artisan opengrc:doctor       # runtime, config, database and build health
php artisan opengrc:migrations:manifest  # regenerate after adding a migration
php artisan opengrc:evidence:verify      # verify evidence hashes and ledger chains
php artisan opengrc:scope profile.json   # framework/control scoping from an organization profile
php artisan opengrc:framework-import --list   # available control libraries
php artisan opengrc:framework-audit  # framework data vs provenance register
```

Docker:

```bash
docker build -t opengrc:local .                    # production image
docker compose -f docker/docker-compose.test.yml run --rm test   # suite in a container
```

`php artisan opengrc:doctor` is the fastest way to tell "my change is broken" from
"this machine is broken". Run it first when something inexplicable fails.

## Layout

| Path | Contents |
|---|---|
| `app/Filament/` | Panels and UI: `Admin`, `Vendor`, resources, forms, tables, widgets |
| `app/Models/` | Eloquent models for standards, controls, implementations, risks, audits, vendors, assets |
| `app/Console/Commands/` | Artisan commands, including `Install` and `Doctor` |
| `app/Services/` | Domain services (scoring, assessment, AI, mail rendering) |
| `Modules/DataManager/` | Module for import/export; enabled in `modules_statuses.json` |
| `database/migrations/` | Schema history. Never edit an applied migration |
| `docs/` | Product and operational documentation, plus decisions |
| `tests/Unit`, `tests/Feature` | PHPUnit suites run in CI |
| `tests/Browser` | Dusk tests; run locally, not in the default CI job |

## Conventions

- **Follow the surrounding code, not a personal style.** Filament resources in
  `app/Filament/Resources` show the expected structure for forms, tables, and
  relations.
- **Migrations are append-only.** New columns and tables get a new migration. If
  you must rewrite data, make the migration idempotent and explain the rollback.
- **Money, scores and dates** are handled with existing casts and helpers. Do not
  invent parallel formatting.
- **Authorization** uses three roles: `admin`, `editor`, `viewer` (`UserRole`).
  Ordinary models are covered by `AppPolicy`; only models with ownership or
  isolation rules get their own policy. New resources need no permission
  registration. Settings and manager pages check permission-style abilities
  (`Manage …`) which `Gate::before` maps to the role.
- **Any user-visible string** belongs in `lang/en/`. Other locales exist; keep the
  keys aligned.
- **Filament is not fully covered by tests.** After changing a resource, open it.

## Non-negotiables

1. Do not run `composer update` or `npm install` as part of a normal change. Update
   lockfiles deliberately, with a note in the PR about what moved and why.
2. Do not bump a major version of Laravel, Filament, Livewire or Vite inside a
   feature PR. Majors go in their own change, per `docs/UPGRADING.md`.
3. Do not edit an already-applied migration. Add a new one.
4. Do not commit `.env`, database files, `storage/` content, or build output.
5. Do not duplicate lab or course content from OpenCyberRange. Reference it; see
   `docs/decisions/0002-training-integration-boundary.md`.
6. Do not add a dependency that is pre-release, abandoned, or unmaintained. Check
   the package, not only the version string.
7. Do not add a dependency outside the license allowlist in `docs/OSS-POLICY.md`.
   Run `php scripts/check-licenses.php` before proposing one.
8. Do not weaken a security default (MFA for privileged roles, closed client
   registration, upload content checks, session encryption, read-only container)
   without an ADR in `docs/decisions/`.
9. Windows contributors: use forward slashes in `DB_DATABASE`. Dotenv treats
   backslashes as escapes. `opengrc:doctor` warns if you forget.

## Definition of done

- [ ] `php artisan test` passes.
- [ ] `./vendor/bin/pint` is clean.
- [ ] `./vendor/bin/phpstan analyse` passes; do not add new baseline entries.
- [ ] If the schema changed, the schema snapshot was regenerated deliberately.
- [ ] `npm ci && npm run build` succeeds if front-end assets changed.
- [ ] `php artisan opengrc:doctor` reports no failures on a fresh install.
- [ ] Framework data changes pass `php artisan opengrc:framework-audit --strict`.
- [ ] New schema has a migration; do not add permission seeders (three-role model).
- [ ] New or changed migrations regenerated `database/migration-manifest.json` in the same change.
- [ ] Docs updated when behaviour, configuration, or supported versions change.
- [ ] If the change is a decision with trade-offs, add an ADR in `docs/decisions/`.

## Skills

Reusable workflows live in `.opencode/skills/`. Load the one that matches the task:
platform architecture, GRC domain model, framework mapping, training scenario
authoring, OpenCyberRange integration, upgrade and compatibility, security,
privacy, design, or user interface.

Security and privacy are review skills as much as build skills. Load them before
changing authentication, uploads, private files, trust center disclosure, exports,
AI prompts, or anything that touches personal data. Load design and user interface
before adding or reshaping a screen.
