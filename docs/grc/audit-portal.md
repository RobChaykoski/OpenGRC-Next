# Auditor portal

An auditor can read one audit without an OpenGRC account. The portal is a
revocable link scoped to a single audit: read-only, logged, and deliberately
without files or bulk export (ADR 0045).

## Issuing a link

1. Open the audit in **Foundations → Audits**.
2. In the **Auditor portal links** tab, choose **Invite auditor**.
3. Name the person or firm and choose an expiry (7, 14, 30, 60 or 90 days).
4. After the link is created, choose **Copy the new link** and copy it from the
   dialog. **It is shown once.** Only its hash is stored.

Only an administrator can issue, rotate or revoke a link. Editors and viewers can
read the grant state and the access log.

## What the auditor sees

| Section | Contents |
|---|---|
| Audit | Title, description, type, status, period, manager, generation time |
| Summary | Item count, completed count, effectiveness split, open finding count |
| Items | Control or implementation code and title, applicability, status, effectiveness, auditor notes |
| Implementations | The organisation-written implementation details, last and next test dates |
| Evidence | Latest five records per control: source, type, status, collection time, `payload_sha256` |
| Validation | Latest three verdicts per control with evaluation time |
| Findings | Open and in-progress findings on the linked controls: reference, severity, status, due date |

Explicitly **not** shown: evidence payloads, attachments and files, data request
responses, internal user contact details, risk registers, licensed framework
control text, and anything outside this audit.

The page is the boundary described in ADR 0016. If an auditor needs the artifact
itself, retrieve it from OpenGRC under access control and verify it against the
hash in the portal.

## Rotating and revoking

- **Rotate link** invalidates the current URL and issues a new one, shown once.
  Use it when a link is lost or forwarded too widely.
- **Revoke** ends access immediately. The grant stays in the list so the access
  log and the decision remain visible.
- Expiry is mandatory. An expired link returns `410` and does not slide forward.

From the console, for an incident or when the admin panel is unavailable:

```bash
php artisan opengrc:audits:portal                 # active links
php artisan opengrc:audits:portal --all           # include expired and revoked
php artisan opengrc:audits:portal --audit=12      # one audit
php artisan opengrc:audits:portal --revoke=7      # kill a link by ID
```

## Access log

Each successful read creates a row in `audit_portal_views` with the time, network
address and user-agent, and increments the grant's open count. The last 20 rows
are visible from **Views** on the grant. Expired and revoked attempts are logged
as warnings in the application event log, not as views.

View rows are retained with the grant. Deleting the audit deletes its grants and
views; deleting a closed engagement's grants is the programme's retention
decision.

## Boundaries and limits

- **A link is a bearer capability.** Anyone who has it can read that audit until
  it expires or is revoked. Send it directly to the named auditor; do not post it
  in a shared channel.
- **Serve the portal over HTTPS.** The token is in the URL path and would be
  readable on the network over plain HTTP.
- **No export.** There is no CSV, PDF or ZIP action. Browser print or save is the
  reader's own copy and is not a platform control.
- **Not a compliance statement.** The portal reports what was tested and what
  evidence exists. It does not score the audit or assert compliance.
- **No email delivery.** The link is copied and sent by the audit manager, using
  the channel the organisation already trusts. There is no invitation email to
  misconfigure.
