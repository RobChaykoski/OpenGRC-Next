# Open discovery console

OpenGRC is the console for discovery. OpenDiscoveryEngine (ODE) stays a headless,
GPL-3.0-only data-plane service; OpenGRC pulls a read-only mirror of its scans and
findings, links them to controls and evidence, and owns the audit trail. See ADR
0018 for the decision and ADR 0011 for the service split.

This is the pull path. The engine's hash-verified evidence push
(`POST /api/v1/evidence`, `docs/integrations/discovery-evidence.md`) remains the
path that writes to the evidence ledger. The mirror does not replace it, and the
two must agree on the finding identifiers.

Phase 1, the read-only mirror, is the current slice. Phase 2 and Phase 3 are
design intent and are not shipped behaviour.

## Boundary

| OpenGRC owns | ODE owns |
|---|---|
| The console: scan and finding views, control links, reviews, exceptions | Scan execution, targets, profiles, detectors, credentials |
| The console mirror of scan and finding state | Raw scan data, match detail, redacted previews and match hashes |
| The evidence ledger and audit history | Agent tokens and their lifecycle |
| Which control a finding is assessed against | Which classification a match carries before review |

Only findings and metadata cross the boundary. No raw file content, matched value,
credential or agent token is copied into OpenGRC.

## How a sync works

1. `opengrc:discovery:sync` calls `GET /api/v1/console/scans` and then
   `GET /api/v1/console/findings` with the configured read-only key.
2. Each record is upserted into the mirror, keyed by tenant plus the engine's
   external id. Re-running a sync is safe.
3. Every mirrored row records `synced_at`; the newest of those is the
   high-water mark of the last successful sync. Doctor and the posture page read
   that timestamp rather than guessing freshness.
4. Rows that disappeared from the engine stay in the mirror; a sync never
   deletes, so a control's history stays readable. Removing a mirror row is a
   deliberate operator action, not a side effect of a scan.
5. A re-sync refreshes engine-owned columns. Review state belongs in OpenGRC and
   is not overwritten by the mirror.

Phase 1 is read-only: the command never writes to the engine. A sync that only
reads cannot corrupt a scan.

## Configuration

OpenGRC:

```dotenv
DISCOVERY_CONSOLE_URL=https://discovery.example.com
DISCOVERY_CONSOLE_TOKEN=<read-only service key>
DISCOVERY_CONSOLE_TENANT=acme
DISCOVERY_CONSOLE_TIMEOUT=15
DISCOVERY_CONSOLE_STALE_AFTER_DAYS=2
```

ODE:

```dotenv
DISCOVERY_CONSOLE_API_KEY=<the key ODE accepts on console requests>
DISCOVERY_CONSOLE_TENANT_SLUG=acme
```

The engine's existing `DISCOVERY_OPENGRC_*` settings are the push direction and
are unchanged; the `DISCOVERY_CONSOLE_*` variables configure the pull.

The key is created in ODE as a read-only, tenant-scoped service credential. It is
a bearer secret: TLS only, stored as a secret, and rotated on a schedule. The
tenant slug must match on both sides; a mismatch is a configuration error, not an
empty result.

One ODE tenant per OpenGRC deployment is the supported shape for this slice.
Multiple tenants, or one console for several deployments, is a later decision.

## Running a sync

```bash
php artisan opengrc:discovery:sync
```

The command prints counts for scans, findings and findings that reference a scan
not yet mirrored, and exits non-zero if the engine is unreachable, the key is
rejected, or any record fails to store. Schedule it rather than running it by
hand:

```cron
*/15 * * * * cd /var/www/opengrc && php artisan opengrc:discovery:sync >> storage/logs/discovery.log 2>&1
```

A 15-minute cadence is a starting point. Discovery is not a real-time control.

## Freshness

The mirror is eventually consistent. The console shows the last successful sync
and marks data stale after a configurable age; `opengrc:doctor` reports the same
state.

A stale mirror is not an empty mirror. A failed sync must leave the previous
findings visible and labelled, never show "no findings" as if the scan had been
clean.

## Contract

The engine's OpenAPI schema is authoritative; the console consumes a stable
subset and ignores unknown fields so the engine can add detail without a console
release.

A scan carries at least: external id, tenant, profile, target summary, state,
started and finished times, and finding counts by severity.

A finding carries at least: external id, scan id, tenant, asset identifier,
classification, severity, state, first and last seen, a redacted preview and the
match hash. It does not carry the matched value.

Console-side status is derived, not copied blindly: an engine scan that never
finished, or a finding for an asset that no longer exists, surfaces as `unknown`
with a reason rather than as a pass.

## Phases

| Phase | Scope | API |
|---|---|---|
| 1 | Read-only mirror of scans and findings; control links are the next slice | `GET /api/v1/console/scans`, `GET /api/v1/console/findings` |
| 2 | Profile, scan and agent-token control from the console | Console control endpoints, idempotent, audited in both systems |
| 3 | Write-back of reviews and dispositions, with a review queue | Reviewed transitions only; no last-write-wins |

Phase 2 introduces the first writes. Each one needs an operator identity, an
OpenGRC audit event and an engine-side record of who asked. Phase 3 does not
bypass the review queue: an external finding becomes an OpenGRC finding when a
person accepts it.

## Failure handling

| Status | Meaning | Action |
|---|---|---|
| `200` | Data returned | Upsert; update the freshness timestamp |
| `401` | Key missing, expired or wrong | Fail loudly, do not retry in a loop |
| `403` | Key is not read-only-console scoped, or wrong tenant | Fail; fix the credential, not the request |
| `429` | Rate limited | Retry honouring `Retry-After` |
| `5xx` / network | Engine unavailable | Retry with backoff; keep the previous mirror |

The console does not send conditional requests, so `304` is not part of the
contract; an unexpected non-2xx status fails the run rather than being read as
"nothing new".

Pagination is followed to completion or the run fails. A truncated list must not
be stored as a smaller estate.

## Security notes

- The service key is read-only and tenant-bound. It cannot start scans or change
  findings, and it cannot read another tenant.
- Browser sessions are not accepted on the console API; it is machine-to-machine
  only.
- Finding previews are already redacted by the engine. OpenGRC must not request
  or store the unredacted match.
- Mirror tables contain asset names and finding metadata. Treat the console as an
  internal system, and keep exports scoped like every other evidence export.
- Rotate the key by issuing a new one in ODE, deploying it to OpenGRC, then
  revoking the old one. Record who holds it.

## Limits and next steps

Phase 1 does not:

- start, cancel or schedule scans (Phase 2);
- rotate or display agent tokens (Phase 2);
- push a review or disposition back to the engine (Phase 3);
- host more than one tenant per deployment (a future decision);
- stream results in real time; the mirror is a scheduled pull.

When a finding is accepted into OpenGRC, link it to a control and let the existing
validation and assurance rules score it; do not duplicate discovery's
classification logic in the console.
