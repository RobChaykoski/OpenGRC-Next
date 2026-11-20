# 0018 — One console: OpenGRC fronts the discovery engine

Status: accepted
Date: 2026-11-20

## Context

ADR 0011 made discovery a separate GPL-3.0 service. The engine can scan, classify
and report, but it is not a compliance system: it has no controls, no evidence
ledger and no audit trail. Operators currently run a scan in one product and read
its results in another, so the question "is this control covered?" is answered by
reconciling two screens that disagree about state and freshness.

Another UI is not the fix. Two dashboards with overlapping content produce two
sets of permissions, two vocabularies for the same finding, and an operator who
still has to switch products. The reverse — folding the engine into OpenGRC — is
worse: it puts GPL-3.0 Python code inside an AGPL PHP application and makes every
OpenGRC release depend on a scan runtime.

## Decision

1. **OpenGRC is the single console and the system of record.** Every
   operator-facing discovery screen lives in OpenGRC. The engine is headless: it
   executes work and owns its data plane, not the console.
2. **The engine stays a separate GPL-3.0-only service, invoked over HTTP.** No
   engine code is copied, vendored or linked into OpenGRC; integration is
   HTTP/JSON only. OpenGRC remains AGPL-3.0-only. This is the "separately invoked
   tool" boundary in `docs/OSS-POLICY.md`, not a dependency.
3. **Tenancy: one engine tenant per OpenGRC deployment for the first slice.** The
   tenant is configuration, not an assumption. Multi-tenant hosting is a separate
   decision, taken before any second tenant is served.
4. **Phase 1 is a read-only mirror.** OpenGRC pulls scans and findings with a
   read-only service key from `GET /api/v1/console/scans` and
   `GET /api/v1/console/findings`. Phase 1 writes nothing to the engine.
   **Phase 2** moves profile, scan and agent-token control behind the same
   boundary. **Phase 3** adds write-back, with a review queue so an external
   finding becomes an OpenGRC finding only after a person accepts it.
5. **Data boundary: findings and metadata only.** The engine stores the raw scan
   output and match detail; OpenGRC stores the console mirror, the control links
   and the evidence ledger. No raw file content, matched value or credential
   crosses the boundary.
6. **Open-source choices throughout.** Console: Laravel, Filament, Livewire,
   Alpine. Engine: Python, FastAPI, SQLAlchemy, httpx. `nmap` and NAPALM are run
   as separate processes rather than imported; the policy allows GPL for
   separately invoked tools. No new proprietary dependency is introduced on
   either side.
7. **Setup is symmetric and explicit.** OpenGRC reads
   `DISCOVERY_CONSOLE_URL`, `DISCOVERY_CONSOLE_TOKEN` and
   `DISCOVERY_CONSOLE_TENANT`; the engine reads `DISCOVERY_CONSOLE_API_KEY` and
   `DISCOVERY_CONSOLE_TENANT_SLUG`. Sync runs as
   `php artisan opengrc:discovery:sync`.
   See `docs/integrations/open-discovery-console.md`.

## Consequences

- Operators get one place to run compliance work: a finding, its control, its
  evidence and its review state are read in one UI. The engine keeps the detail
  view and stays authoritative for scan execution.
- Two runtimes, one UI: two deployables, two upgrade cadences, and health,
  logging and backup work in both. Checks must be visible in
  `opengrc:doctor`, not discovered when a page looks empty.
- Phase 1 is cheap and reversible: read-only, no schema coupling, and removing it
  deletes a mirror rather than restoring a system. Losing the mirror loses the
  console view, not the evidence ledger.
- Phase 2 is real control-plane work: a new engine API, idempotent commands, a
  permission model for who may start or cancel a scan, and an error surface that
  does not blame OpenGRC for an engine refusal.
- The mirror is eventually consistent. A failed sync must read as stale, never as
  "no findings"; the last successful sync time is part of the console contract.
- Token rotation becomes a two-sided operation. The service key is read-only and
  tenant-bound; a leaked key exposes one tenant's findings, so rotation has an
  owner and a schedule.
- Tenancy is deliberately deferred. The first slice carries a tenant slug in
  configuration and in mirrored rows so a future multi-tenant decision is a
  migration with data, not a rewrite.
- Until Phase 3, the engine wins any conflict. Write-back needs the review queue
  rather than last-write-wins, because a mirrored finding and a GRC finding are
  not the same record.

## Alternatives considered

- **Merge the codebases.** Rejected: GPL-3.0 obligations would reach the whole
  console, a Python scan runtime would enter the PHP release, and the tenant
  model of the engine does not match the OpenGRC installation model.
- **Embed the engine UI in an iframe.** Rejected: no shared data model, no shared
  session or permissions, broken deep links and accessibility, and the operator
  still has two mental models of the same finding.
- **Build a third dashboard or portal over both.** Rejected: it becomes a link
  farm with its own auth and no control context, which is the problem it claims
  to solve.
- **Keep the engine UI as the console and link to OpenGRC.** Rejected: a
  compliance answer needs controls, evidence and audit history, none of which the
  engine owns.
