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
php artisan opengrc:framework-import --list   # available control libraries
php artisan opengrc:framework-audit  # framework data vs provenance register
```

`php artisan opengrc:doctor` is the fastest way to tell "my change is broken" from
"this machine is broken". Run it first when something inexplicable fails.

## Layout

| Path | Contents |
|---|---|
| `app/Filament/` | Panels and UI: `Admin`, `Vendor`, resources, forms, tables, widgets |
| `app/Models/` | Eloquent models for standards, controls, implementations, risks, audits, vendors, assets |
| `app/Console/Commands/` | Artisan commands, including `Install` and `Doctor` |
| `app/Mcp/` | MCP server tools exposed to AI clients |
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
- **Permissions** go through `spatie/laravel-permission` and the seeders that
  register them. A new resource needs its permissions seeded, or it will be
  invisible to non-super-admins.
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
7. Windows contributors: use forward slashes in `DB_DATABASE`. Dotenv treats
   backslashes as escapes. `opengrc:doctor` warns if you forget.

## Definition of done

- [ ] `php artisan test` passes.
- [ ] `./vendor/bin/pint` is clean.
- [ ] `./vendor/bin/phpstan analyse` passes; do not add new baseline entries.
- [ ] If the schema changed, the schema snapshot was regenerated deliberately.
- [ ] `npm ci && npm run build` succeeds if front-end assets changed.
- [ ] `php artisan opengrc:doctor` reports no failures on a fresh install.
- [ ] Framework data changes pass `php artisan opengrc:framework-audit --strict`.
- [ ] New schema has a migration; new permissions have a seeder.
- [ ] Docs updated when behaviour, configuration, or supported versions change.
- [ ] If the change is a decision with trade-offs, add an ADR in `docs/decisions/`.

## Skills

Reusable workflows live in `.opencode/skills/`. Load the one that matches the task:
platform architecture, GRC domain model, framework mapping, training scenario
authoring, OpenCyberRange integration, or upgrade and compatibility.
