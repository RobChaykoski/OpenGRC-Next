# Production promotion

This is the runbook for moving a tagged OpenGRC build onto a real environment.
It is not the local Compose stack, and it is not a substitute for a change
process.

`opengrc:doctor --strict` is the gate at the end. It treats a warning as a
failure, which is deliberate: a deployment where the checks "mostly" pass is the
one that gets promoted with an unenrolled administrator.

## 1. Preconditions

- [ ] The release is tagged (`v0.1.0-alpha-N`) and the tag is pushed.
- [ ] `php artisan test`, Pint and PHPStan pass on the tag.
- [ ] The change window and a named owner are agreed.
- [ ] The backup from step 2 has been restored into a scratch environment at
      least once. A backup nobody restored is a hope.
- [ ] Every administrator has enrolled a second factor. There is no CLI shortcut;
      enrolment happens in **Profile → Two-factor authentication**. The doctor
      names any admin who has not.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, a stable `APP_KEY`, HTTPS with HSTS,
      `SESSION_ENCRYPT=true` and secure cookies.
- [ ] The seeded/demo account (`admin@opengrc.local` or similar) is deleted or has
      a real owner and 2FA.

## 2. Backup

Take a database dump and a storage snapshot before touching code:

```bash
# MySQL
mysqldump --single-transaction --routines --triggers \
  -u <user> -p <database> > opengrc-$(date +%F).sql

# The storage volume holds evidence files, the application key and logs
tar -czf opengrc-storage-$(date +%F).tar.gz /var/www/opengrc/storage
```

Record the dump's hash and location in the change record. Restore both into the
scratch environment from step 1 and confirm the login page and one evidence
download work.

## 3. Deploy

Install exactly the locked dependencies; never `composer update` or `npm install`
during a release:

```bash
git fetch --tags
git checkout v0.1.0-alpha-N
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

For the reference container, build the image from the tag instead:

```bash
docker build -t opengrc:v0.1.0-alpha-N .
```

Keep the previous image tagged. It is the rollback path.

## 4. Verify

```bash
php artisan opengrc:migrations:verify --strict
php artisan opengrc:evidence:verify
php artisan opengrc:framework-audit --strict
php artisan opengrc:doctor --strict
```

Expected on a clean install: migrations "0 pending, checksums verified", framework
audit "0 warnings", doctor "0 fail, 0 warn". A doctor warning is a failure for this
gate; fix it or record an explicit, dated acceptance with the owner's name.

Smoke, with a real account:

| Check | Expected |
|---|---|
| `GET /app/login` | 200, no stack trace, HTTPS |
| Login as an admin | 2FA challenge, then the panel |
| `GET /api/user` without a token | 401 JSON, not a redirect |
| `POST /api/v1/access/decisions` without a token | 401 JSON |
| Trust center with an expired link | 410 or 404, no document body |
| Private file URL tampering | 403/404, no file |
| `GET /audit-portal/invalid` | 404 |
| Queue | a queued job runs; `failed_jobs` stays empty |
| Scheduler | `php artisan schedule:list` shows the expiry and snapshot commands |

## 5. After the window

- Watch `failed_jobs`, the queue backlog and `storage/logs` for the first hour.
- Confirm the scheduler runs (grant expiry, DLP suppression expiry, assurance
  snapshot, privacy deadlines).
- Record the promoted tag, the doctor output, the smoke results and the backup
  location in the change record.
- If the change is user-visible, note the screens opened by hand and at which
  role; the test suite does not cover Filament rendering.

## 6. Rollback

Rollback is "restore the previous tag and the backup", not `migrate:rollback`.
Migrations are append-only: a schema change from this release cannot be reversed
by `down()` once real data exists.

1. Stop the queue worker.
2. Restore the database dump and, if files changed, the storage snapshot.
3. Deploy the previous image or tag.
4. `php artisan queue:restart`, then re-run the step 4 checks on the old build.
5. Record what happened; if the failure was a defect, it gets a test before the
   next attempt.

## 7. What the gate does not prove

- Doctor checks configuration and evidence freshness, not application security.
  It is not a penetration test.
- It does not test the backup or the restore; step 2 does.
- It does not prove the WAF is blocking. The reference Compose deployment runs
  ModSecurity in detection-only mode; switching to blocking is a separate
  decision after reviewing false positives.
- It does not enrol a second factor for you. An admin without 2FA stays a
  warning, and a warning fails `--strict`.
- The local Compose stack in `docker-compose.yml` seeds `admin@opengrc.local`
  without 2FA on purpose, for a throwaway development environment. It is expected
  to fail `--strict` there and must never be promoted as-is.
