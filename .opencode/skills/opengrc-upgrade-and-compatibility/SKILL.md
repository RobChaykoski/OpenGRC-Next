---
name: OpenGRC Upgrade and Compatibility
description: Use when upgrading PHP, Laravel, Filament, Livewire, Vite, Node, or the database, or when a change might break an existing installation. Covers the supported baseline, upgrade tiers, compatibility gates, rollback, and the doctor command.
---

# OpenGRC Upgrade and Compatibility

The aim is boring upgrades: predictable, reversible, and tested on the versions we
claim to support. Sibling projects learned these lessons expensively; apply them
instead of rediscovering them.

Authority documents:

- `versions.yaml` — the tested baseline (machine-readable)
- `docs/SUPPORTED-VERSIONS.md` — the policy and known traps
- `docs/UPGRADING.md` — the step-by-step runbook

## Classify before you start

| Tier | Examples | Gate |
|---|---|---|
| 1 — patch/minor | Symfony patch, Laravel minor, npm patch | Tests + build + doctor |
| 2 — build | Node, Vite, Tailwind | `npm ci && npm run build` on supported Node lines |
| 3 — framework majors | Laravel, Filament, Livewire | Own PR, upgrade guide read, manual smoke test |
| 4 — runtime/data | PHP minor, database major | Restore-tested backup, `migrate --pretend`, rollback plan |

If a change spans tiers, split it.

## Non-negotiables

- `composer install` in CI and deploy. `composer update` is a deliberate,
  reviewed change that lands a lockfile diff.
- `npm ci` everywhere except local exploration.
- No pre-release, abandoned, or unmaintained dependencies.
- Lockfiles are committed. They are the build.
- Majors never ride along with features.
- A frozen old version needs a written exception in `versions.yaml`.

## Always run, in this order

```bash
composer validate --strict
composer install --prefer-dist
composer audit --locked --no-dev
php artisan test
npm ci && npm run build
php artisan opengrc:doctor --strict
```

`--strict` promotes warnings to failures, which is what CI should do. Locally, use
the default mode so a missing build does not look like a code failure.

## Compatibility checks that catch real breakage

1. **The floor, not your machine.** Test on the minimum supported PHP, not only
   the newest. CI runs a matrix for exactly this reason.
2. **Both SAPIs.** CLI extensions can differ from the web SAPI. A missing `intl` in
   FPM but present in CLI is a classic.
3. **Windows paths.** Forward slashes in `DB_DATABASE`; Dotenv parses backslashes as
   escapes. `DatabasePath` normalises writes and the doctor flags reads.
4. **Migrations on SQLite.** Test upgrade migrations against a copy of a real
   SQLite database, not only a fresh one. Table rebuilds are slower and stricter.
5. **Cached config.** `config:cache` bakes `.env` in. Any env change needs
   `optimize:clear`.
6. **Queue workers.** Old code lives in worker memory. `php artisan queue:restart`
   after every deploy, or you will run two versions at once.
7. **Vite manifest.** Deploy must build assets; a stale or missing
   `public/build/manifest.json` looks like a styling bug and is not.
8. **Module migrations.** `php artisan module:migrate --force` for enabled modules;
   module views are the least-tested surface after a Filament upgrade.

## Rollback checklist

- [ ] Previous tag identified (`git describe --tags`).
- [ ] Previous lockfiles available (they are in git).
- [ ] Database backup taken **and restored once** into a scratch database.
- [ ] Know whether the release's migrations have a working `down()`.
- [ ] Runtime previous version still installed for tier 4 changes.
- [ ] `queue:restart` planned.

Code rollback without data rollback is only safe when the schema did not change.

## Post-upgrade record

Update `versions.yaml` and `docs/SUPPORTED-VERSIONS.md` in the same change when the
baseline moves. If a decision had trade-offs, add an ADR. A future maintainer
should be able to read why, not guess.

## When something breaks

Run `php artisan opengrc:doctor` first. It separates environment problems from code
problems and prints the release identity. Then:

1. Reproduce on a fresh clone with the locked dependencies.
2. Check `storage/logs/laravel.log` for the first exception, not the last.
3. Confirm the failing version against `docs/SUPPORTED-VERSIONS.md`.
4. If the fix is a dependency bump, treat it as tier 1: lockfile diff, tests, doctor.
