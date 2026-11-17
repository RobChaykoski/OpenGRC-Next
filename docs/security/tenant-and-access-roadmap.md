# Tenant isolation, access control, and security analytics: implementation plan

Status: proposed. Each numbered workstream needs an ADR before code that changes
the schema or the authorization model.

Order matters: tenancy changes every query; ABAC changes every policy; the audit
subsystem and dashboard read from both. Building the dashboard first produces
numbers nobody can trust.

## 1. Multi-tenant security

### Model

```
Tenant (organization)
  -> BusinessUnit
  -> User membership (user_id, tenant_id, role)
  -> all tenant-owned records
```

Every major table gets:

- `tenant_id` (non-null, indexed, foreign key)
- `created_by`, `updated_by` (nullable, foreign key to users)
- `classification` (public/internal/confidential/restricted)

Tables in scope: controls, implementations, risks, mitigations, policies,
exceptions, assets, applications, vendors, vendor users/documents, audits, audit
items, data requests, responses, evidence, findings, programs, standards imported
per tenant, checklists, surveys.

Reference data (framework definitions) is global; the tenant-owned
implementation of it is not. A tenant can import the same standard without
sharing its controls or evidence.

### Rules

1. `tenant_id` is assigned server-side from the authenticated membership. Never
   read it from a request body, query string, header, or hidden form field.
2. A global scope applies `where tenant_id = current tenant` to every tenant
   model. Console commands and cross-tenant admin tools must opt out explicitly
   with a named scope, not by accident.
3. Unique constraints include `tenant_id`. Two tenants can both have `POL-001`.
4. File storage paths include the tenant id. A signed URL from one tenant must
   never resolve for another.
5. Cross-tenant references are rejected at validation and at the policy layer.
6. Tests assert isolation in both directions: tenant A cannot read, update,
   delete, export, or download tenant B, even with a guessed id.

### Migration

- Create a default tenant.
- Backfill every existing row to it.
- Make `tenant_id` non-null only after the backfill.
- Keep the old single-tenant deployment working as a one-tenant install.

### UI

- Tenant switcher only for users with memberships in more than one tenant.
- Current tenant shown in the header; every page title carries it in exports.
- System admin can see tenant metadata, not tenant content, unless explicitly
  impersonating with an audit entry.

## 2. RBAC plus attribute-based access

Baseline roles: system admin, tenant admin, risk manager, compliance officer,
auditor, policy owner, asset owner, control owner, vendor manager, read-only.

RBAC answers "what kind of action"; ABAC answers "on which records":

- department = user's department or an allowed department
- classification <= user's clearance
- region in user's regions
- ownership (policy owner, control owner, asset owner)
- audit membership
- explicit deny always wins

Evaluation order:

1. system/tenant admin override (audited)
2. explicit deny
3. record attributes
4. role permission
5. default deny

Every policy gets a matrix test. A viewer must not see a row they cannot open, and
an export must apply the same rules as the table.

This supersedes ADR 0007 in scope, not necessarily in spirit: the three roles stay
as a simple mode until the tenant/ABAC model is accepted.

## 3. Evidence protection

Implemented in the evidence ledger (ADR 0008):

- SHA-256 on upload, uploader, timestamp, append-only chain
- withdrawal with a reason, supersede with a new file
- production latch and verification command

Next: explicit `version` on evidence, a visible chain-of-custody panel, retention
rules per classification, and scheduled verification with alerting.

## 4. Immutable audit subsystem

`activity_log` is useful but mutable. Add `audit_events`:

- id, tenant_id, occurred_at, actor_type/id, ip, user_agent, action, subject
- old_values/new_values (redacted), request_id, app_version
- previous_hash, entry_hash
- append-only trigger; no update/delete path
- coverage: login, logout, failed login, role change, user change, risk/control/
  policy/evidence/audit changes, exports, settings changes

Keep operational logs separate. The audit trail is evidence; logs are telemetry.

## 5. Security dashboard

Metrics become trustworthy once 1 and 4 exist:

- overdue policy reviews, control failures, expiring risk acceptances
- failed logins, role changes, dormant privileged accounts
- unauthorized access attempts (denied policy checks)
- evidence verification failures and unledgered evidence
- tenant isolation violations

Dashboards must respect the same policies as the underlying records. A count the
viewer cannot drill into is a leak.

## Sequencing and exit criteria

| Phase | Work | Exit |
|---|---|---|
| A | Tenant schema, default tenant, global scope, isolation tests | Two tenants cannot see each other by id or export |
| B | Membership roles + ABAC attributes, policy matrix tests | Every resource and export obeys the matrix |
| C | Audit event ledger with hash chain and append-only trigger | Tamper test fails verification; login/role changes covered |
| D | Evidence version UI, retention, scheduled verification | Chain-of-custody visible and alerts fire |
| E | Security dashboard | Every metric drills into permitted records |

Labs and course content remain in OpenCyberRange (ADR 0002). OpenGRC provides the
tenant, records, evidence, audit trail, and grading state that scenarios reference.

## Non-negotiables

- No client-supplied tenant id, ever.
- No query that bypasses the tenant scope without a named, reviewed reason.
- No policy that returns "allow" by default.
- No mutable audit row.
- No dashboard count over records the viewer cannot open.
