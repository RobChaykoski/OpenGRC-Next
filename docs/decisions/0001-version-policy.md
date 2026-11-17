# 0001 — Supported-version policy and upgrade tiers

Status: accepted
Date: 2026-11-17

## Context

OpenGRC depends on a fast-moving stack: Laravel, Filament, Livewire, Vite and
Tailwind all ship majors that break APIs. Installations live for years in small
teams that do not have a platform engineer. Without a written baseline, upgrades
happen by accident (the `composer update` on deploy), by drift (two machines
resolving different versions), or not at all until the runtime is EOL.

A sibling project, OpenCyberRange, hit all three of those failure modes and
settled on an explicit baseline: pin the runtime floor, keep lockfiles canonical,
treat majors as projects, and record exceptions rather than hiding them.

## Decision

1. Adopt a tiered baseline recorded in `versions.yaml` and explained in
   `docs/SUPPORTED-VERSIONS.md`.
2. "Supported" means stable, still maintained upstream, and covered by tests — not
   necessarily newest.
3. Group upgrades into four tiers by blast radius: patches, build tooling,
   framework majors, and runtime/data. Never mix tiers in one change.
4. `composer.lock` and `package-lock.json` are the dependency pins. Deploy with
   `composer install` and `npm ci`.
5. Every supported version has a review date. Freezes require a written exception.
6. `php artisan opengrc:doctor` is the executable form of the baseline, so the
   document and reality cannot drift apart silently.

## Consequences

- Slower adoption of new releases. Accepted: predictable upgrades matter more than
  being current.
- CI must test the floor and the next minor, which costs a little build time.
- Contributors cannot casually bump a major in a feature PR. That is the point.
- A stale baseline is now visible and actionable instead of invisible.

## Alternatives considered

- **Let Composer resolve freely on deploy.** Rejected: non-reproducible production
  builds and no rollback story.
- **Pin exact versions of everything.** Rejected: security patches stop arriving,
  and the pin becomes a fork in everything but name.
- **Track only major versions.** Rejected: most breakage is minor-version drift in
  tightly coupled packages such as Filament.
