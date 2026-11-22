# 0057 — Multi-tenancy is a deployment decision, not a nullable column

- Status: proposed
- Date: 2026-11-22

## Context

OpenGRC is single-tenant by design today: one organisation per deployment.
Managed-service providers run separate instances, which is the supported answer
and the one `docs/security/tenant-and-access-roadmap.md` describes.

The tempting shortcut is a nullable `tenant_id` on every table plus a global scope
that skips filtering when the column is null. That is not isolation. It is a
column that looks like isolation, and the first query that uses `withoutGlobalScope`,
runs in a console command, or joins across the wrong table leaks another tenant's
evidence. The roadmap explicitly warns against it.

A shared instance is a different product boundary: it changes every query, every
export, every policy, every file URL, every queued job and every AI prompt.

## Decision (proposed)

1. **One tenant per deployment stays the default.** A shared instance is an
   explicit, licensed feature with its own boundary and tests, not a side effect
   of a column.
2. **`tenants` plus a non-null `tenant_id`.** Every tenant-owned table gets
   `tenant_id` as a foreign key, indexed, and part of each unique constraint. A
   backfill creates the default tenant in the same migration that makes the
   column non-null. There is no permanent nullable state.
3. **The tenant is assigned server-side** from the authenticated membership or the
   credential that opened the request. It is never read from a request parameter,
   header or form field.
4. **A base model applies the scope.** Tenant-owned models extend a base class
   whose global scope filters by the current tenant. Raw queries, console
   commands and cross-tenant administration opt out explicitly and visibly, with
   a test for each opt-out.
5. **Cross-tenant references are rejected twice:** at validation when a foreign id
   is submitted, and at the policy layer when a record is opened. An existing
   record from another tenant is a 404, not a 403, so the response does not
   confirm that it exists.
6. **Storage is tenant-scoped.** Private paths include the tenant, file
   authorization requires the tenant to match, and a signed URL is scoped to one
   tenant. Public assets stay global and deliberately public.
7. **Settings divide explicitly.** Each setting is global (deployment) or
   tenant-scoped, never "whatever the caller set". Provider credentials are
   encrypted and scoped; one tenant's key is never used for another.
8. **Work carries its tenant.** Every queued job stores the tenant id and
   re-establishes context on run. Scheduled commands iterate tenants; a command
   that cannot name its tenant does nothing.
9. **Portals resolve their own tenant.** A vendor user, an auditor capability
   link and a trust-center host all resolve the tenant from their credential or
   host, never from a path segment a caller can change.
10. **Missing context denies.** No tenant in context is a 403/500 for a
    tenant-owned query, never an unfiltered result.

## Consequences

- The migration is large and one-way per table. It must land with the backfill and
  the non-null constraint together, or an installation is left in the state this
  ADR rejects.
- Every policy, export, report, dashboard, AI prompt and file path needs a review
  for the tenant dimension. The tests are adversarial: tenant A cannot read,
  update, delete, export or download tenant B with a guessed id.
- Separate-instance deployments remain fully supported and stay the recommended
  shape for a small provider. Multi-tenancy is for an operator who needs one
  console across many organisations.
- Cross-tenant administration (an MSSP view) becomes its own permission with its
  own audit trail, not a quiet `withoutGlobalScope`.
- Nothing in this ADR is implemented. It is the decision the roadmap asks for
  before code, so that the first migration is not the experiment.

## Alternatives considered

- **Nullable `tenant_id` with a "skip when null" scope.** Rejected: it fails open,
  and a forgotten scope is a leak rather than an error.
- **Database per tenant.** The strongest isolation and worth offering to large
  customers, but it multiplies operational work and cannot back a single console
  without a routing layer. Not the default for this application.
- **Schema per tenant.** Middle ground with the same migration and connection
  complexity, and it still needs every query change.
- **Keep instances separate forever.** Honest for many deployments; it does not
  answer an operator who needs one console, and it is what this ADR leaves as the
  default until the shared path is accepted and built.

## References

- `docs/security/tenant-and-access-roadmap.md` — the implementation plan this
  ADR gates
- ADR 0007 — three-role authorization
- ADR 0047 — platform access rules
- ADR 0056 — audit ledger (a tenant dimension must reach it before a shared
  deployment is credible)
