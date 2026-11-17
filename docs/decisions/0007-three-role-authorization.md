# 0007 — Replace granular RBAC with three roles

Status: accepted
Date: 2026-11-17

## Context

Authorization grew into the largest maintenance surface in the codebase:

- 70+ generated permission strings across 14 entities and 5 actions
- 30 hand-written policy classes, several with duplicated ownership logic
- a role-permission matrix page, a role resource, a permission resource, and two
  relation managers to keep them consistent
- a seeder that had to run in the right order relative to the installer

For a small team this is a permanent tax, and it failed in practice: the
authorization matrix test found a resource with no policy at all. Granularity
that nobody can maintain is not security, it is the appearance of security.

The deployments that actually exist are one of two shapes: a single trusted team,
or an MSSP with separate instances per client. Neither needs 70 permission
strings. Both need a model that is obviously correct.

## Decision

Three roles, one column, one default policy:

| Role | Can |
|---|---|
| **Admin** | Everything: settings, users, deletions, trust center, vendor portal accounts |
| **Editor** | Create and update records; cannot delete, manage users, or change settings |
| **Viewer** | Read-only |

Implementation:

- `users.role` column (`admin` / `editor` / `viewer`), backfilled from the old
  Super Admin and Security Admin roles before the Spatie tables are dropped.
- `App\Policies\AppPolicy` covers ordinary models. Six models keep explicit
  policies because they have ownership or isolation rules: audit items, data
  requests, data request responses, file attachments, users, vendor users.
- `Gate::before` maps legacy permission-style ability strings (`List …`,
  `Create …`, `Manage …`) onto the role, so settings pages and widgets did not
  need editing.
- Role/permission resources, the matrix page, and the permission seeder are
  removed. `spatie/laravel-permission` stays installed only because historical
  migrations reference its models; no application code uses it. It can be
  dropped after the migration history is squashed.
- SSO auto-provisioning maps the configured legacy role onto a role value.

## Consequences

- Access rules fit on one screen and are covered by a matrix test.
- Separation of duties is preserved at the level that matters: viewers cannot
  write, editors cannot delete or administer, and admins can do both. It is
  coarser than before; a "can edit controls but not risks" role is no longer
  possible. That is the accepted trade-off.
- Vendors see only the vendor portal; the internal three roles do not affect that
  isolation, which lives in the separate `VendorUser` guard.
- Existing custom roles are collapsed: Super Admin and Security Admin become
  admin, everyone else becomes viewer. Deployments that relied on a read-only
  Regular User keep that behaviour; anyone who needs to write becomes an editor.
- Trust-center and vendor-management access is now admin-only. If a deployment
  needs a "trust center editor", that is a fourth role, and it needs an ADR.
- Deleting the permission tables is irreversible. The upgrade runbook requires a
  backup; the migration's `down()` intentionally does nothing.
- `spatie/laravel-permission` remains as a transitive migration dependency until
  migrations are squashed, at which point it should be removed entirely.

## Alternatives considered

- **Keep granular permissions and generate the policies.** Reduces drift but not
  the conceptual surface; still 70 strings to reason about.
- **Single role (everyone is admin).** Simplest, but loses separation of duties,
  which is a core GRC requirement and a training objective.
- **Four roles (add Trust Center Editor).** Genuinely useful for larger teams, but
  no current deployment needs it; add it when someone asks, with an ADR.
