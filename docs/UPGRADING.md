# Upgrading OpenGRC

This is the runbook for moving an OpenGRC installation forward. It assumes you
already read `docs/SUPPORTED-VERSIONS.md`.

The short version:

```
backup -> install pinned deps -> migrate -> rebuild assets -> restart workers -> verify -> keep the old copy
```

Do not skip the backup because the upgrade "looks small". The expensive upgrades
are the ones nobody expected to be expensive.

## Before you start

- [ ] Read the release notes and the upstream upgrade guide for any major version
      in the change.
- [ ] Confirm the target PHP, Node, and database versions against
      `docs/SUPPORTED-VERSIONS.md`.
- [ ] Take a database backup and copy `.env` somewhere safe.
- [ ] Restore that backup into a scratch database once. If the restore fails, stop
      and fix backups before touching production.
- [ ] Note the current release: `git describe --tags --always --dirty`.
- [ ] Schedule a window. Migrations are not instantaneous on large installs.
- [ ] Put the app in maintenance mode if it has live users.
- [ ] Expect one forced re-login: sessions are encrypted by default from the
      security-defaults release. Nothing is lost; existing cookies are invalid.

```bash
php artisan down --retry=60
```

## Step 1 — Get the code

```bash
git fetch --tags
git checkout <target-tag-or-branch>
```

Never upgrade by copying files over an existing installation. Stale files are the
most common source of "it works on a fresh install but not in production".

## Step 2 — Install pinned dependencies

```bash
composer install --no-dev --prefer-dist --no-interaction
```

Production uses `install`, not `update`. `update` resolves new versions and turns
every deploy into an untested build.

If the release explicitly changes dependencies, update the lockfile in
development, commit it, and let production install that commit.

## Step 3 — Environment and configuration

- Compare `.env.example` with your `.env` and add any new keys. Missing keys
  usually surface as confusing runtime errors, not clear ones.
- **Windows:** use forward slashes in `DB_DATABASE`. Dotenv parses backslashes
  inside quotes as escape sequences.
- If a key changed meaning, set it explicitly. Do not rely on a default silently
  changing behaviour.
- Clear caches after any env change:

```bash
php artisan optimize:clear
```

Do not run `config:cache` until the app boots without it.

## Step 4 — Database

```bash
php artisan migrate --pretend
```

Read the output. Then:

```bash
php artisan migrate --force
```

If the release adds or changes tables, a test will fail because the committed
schema snapshot no longer matches. That is deliberate: regenerate it only after
reviewing the migration.

```bash
UPDATE_SCHEMA_SNAPSHOT=1 php artisan test --filter=SchemaSnapshotTest
```

For anything that rewrites data:

- Take a second backup immediately before running it.
- Run it against a copy first and time it.
- Know the rollback. Some data migrations are one-way; if there is no rollback,
  the backup is the rollback.

## Step 5 — Modules

OpenGRC ships modules under `Modules/` managed by `nwidart/laravel-modules` and
enabled in `modules_statuses.json`.

```bash
php artisan module:list
php artisan module:migrate --force
```

After a Filament or Livewire major upgrade, verify each enabled module's panel
navigation and one create/edit flow. Module views are the least-covered code in
the test suite.

## Step 6 — Assets

```bash
npm ci
npm run build
```

Use `npm ci`. It installs exactly the lockfile and fails loudly on drift, which is
what you want during an upgrade.

If `public/build/manifest.json` is stale or missing, the UI renders unstyled and
the failure looks like a permissions problem.

## Step 7 — Queue workers and scheduler

Queue workers hold old code in memory. Restart them, or you will run a mix of old
and new behaviour and blame the new release.

```bash
php artisan queue:restart
```

If you use Supervisor or a service manager, restart the worker process there as
well. Confirm the scheduler is still running Laravel's `schedule:run` every minute.

## Step 8 — Verify

```bash
php artisan opengrc:doctor
php artisan opengrc:framework-audit
php artisan about
```

Then complete the manual smoke test:

1. Log in with a normal (non-admin) account.
2. Open a control, a risk, and an audit.
3. Create and edit one record, then delete it.
4. Upload one file (policies and evidence exercise storage).
5. Trigger one queued job (import, export, or notification).
6. Confirm the dashboard widgets render.

```bash
php artisan up
```

## Rollback

Rollback is code plus data plus runtime. Plan all three.

| Layer | Rollback |
|---|---|
| Code | `git checkout <previous-tag>` |
| Dependencies | `composer install` from the previous lockfile; `npm ci` and rebuild |
| Data | Restore the backup. Only roll migrations back if the release provides a tested `down()` |
| Runtime | Reinstall the previous PHP/Node line if the upgrade required a major |
| Queue | `php artisan queue:restart`, then re-check for orphaned jobs |

Rolling back code without rolling back data rarely works after a schema change.
When in doubt, restore the backup and accept the lost writes.

## After the upgrade

- Record what changed and what broke in the PR or release notes.
- If the upgrade changed a policy (dropped a supported version, froze a package),
  update `versions.yaml` and `docs/SUPPORTED-VERSIONS.md` in the same change.
- If a decision had trade-offs, write it down in `docs/decisions/` so the next
  upgrade does not relitigate it from memory.

## Upgrade cadence

| Cadence | Work |
|---|---|
| Every merge | Tests, build, `opengrc:doctor` in CI |
| Monthly | Dependency dashboard, patch/minor updates, security advisories |
| Quarterly | Re-validate the baseline table, check EOL dates, refresh this runbook |
| Yearly | Runtime major (PHP) and framework major (Laravel/Filament) planning |
