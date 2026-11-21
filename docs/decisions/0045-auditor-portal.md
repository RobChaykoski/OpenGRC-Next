# 0045 — Auditor portal access is a revocable link scoped to one audit

Status: accepted
Date: 2026-11-20

## Context

Milestone C.3 of the continuous assurance roadmap asks for an auditor portal that
is scoped to one audit, read-only, logged, and without bulk export. The obvious
implementations are all worse than they look:

1. **Give the external auditor an internal account.** That adds a fourth identity
   population with a role, password, MFA state and session, and every policy in
   the platform has to be re-checked against it. The three-role model (ADR 0007)
   does not describe an external auditor, and nor does the vendor portal (ADR
   0005, vendor guard), which exists for a different relationship.
2. **Rely on the evidence pack ZIP.** The pack is framework-scoped, not
   audit-scoped, and it is a file. Once sent, revocation is impossible and the
   recipient has a copy of every index row it contained (ADR 0016).
3. **Expose the audit through the public trust center.** The trust center is a
   disclosure surface for documents, not a live view of an audit in progress.

The requirement is narrow: an auditor needs to read one engagement's working
papers and evidence metadata while it is happening, and the organisation needs to
be able to end that access and show who used it.

## Decision

1. Access is a **grant tied to exactly one audit**. A grant has a required expiry,
   can be revoked at any time, and never widens to another audit or to the rest
   of the platform.
2. The grant is a **64-character link token**, not an account. Only its SHA-256 is
   stored. The raw link is displayed once, immediately after issue or rotation.
   A lost link is rotated, not recovered.
3. **Every successful read is logged** in `audit_portal_views` with timestamp,
   network address and user-agent, and in the application event log. An expired or
   revoked link is rejected with `410` and logged as a warning; it does not count
   as a view.
4. The portal is **read-only and bounded by the evidence pack rules**: audit
   metadata, audit items, applicability, status, effectiveness, auditor notes,
   control and implementation identifiers, organisation-written implementation
   detail, evidence metadata with SHA-256, validation verdicts and open findings.
   It never renders an evidence payload, an attachment, a person's contact
   details, or licensed framework control text, and it offers no download, print
   or bulk-export action.
5. **Issuance is admin-only.** Editors and viewers can see grant state and the
   access log so they can answer questions, but only an administrator can create,
   rotate or revoke a link.
6. The public route is rate-limited, sends `Referrer-Policy: no-referrer`,
   `X-Robots-Tag: noindex, nofollow, noarchive` and `Cache-Control: no-store`, and
   does not put the token anywhere except the request path.
7. Deleting an audit cascades to its grants and views. There is no orphan portal.

## Consequences

- No new authentication surface: no fourth guard, no external user table, no
  password reset flow to defend.
- A link is a bearer capability. Anyone who obtains it can read that one audit
  until expiry or revocation. The organisation decides who receives it, and the
  view log shows use rather than identity.
- The portal is less convenient than a ZIP: no files, no batch export. That is
  deliberate and matches ADR 0016. Retrieve an artifact under access control and
  verify it against the hash shown in the portal.
- A leaked link cannot be silently reissued: rotation invalidates it and produces
  a new one, and the access log keeps the earlier views under the same grant.
- Evidence metadata shown in the portal can go stale while the link is open. The
  page states when it was generated; it is a live read, not a snapshot.
- Browser printing cannot be prevented. "No bulk export" means no platform-provided
  export; the page is designed to be read, not to assemble a deliverable.
- View logs contain IP addresses and user-agent strings. They are retained with
  the grant; the programme decides when to delete a closed engagement's grants.
