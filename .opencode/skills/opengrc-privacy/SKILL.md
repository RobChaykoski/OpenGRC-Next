---
name: OpenGRC Privacy
description: Use when personal data could enter OpenGRC - user accounts, vendor contacts, trust center access requests, survey answers, evidence attachments, comments, exports, or AI prompts - or when deciding retention, redaction, access, or deletion. Covers the data map, the controls that exist, and the privacy work the product does not do for you.
---

# OpenGRC Privacy

OpenGRC is a GRC system of record. It is not a privacy management platform: it
has no consent registry, no data-subject request workflow, and no automated
retention engine. Treat it as a place where personal data *will* arrive by
accident, and design so that accident stays limited, attributable, and
retrievable.

The distinction that prevents most mistakes:

- **Data Request** in OpenGRC is an audit request for evidence. It is *not* a
  GDPR/CCPA data-subject access request. Do not promise a response deadline or a
  legal basis because the object is called a request.
- **Trust Center access request** is a prospect asking for protected documents.
  It stores their name, email, company, and reason. That is personal data.
- **Vendor survey** answers and attachments routinely contain named employees,
  customer lists, or incident details. The vendor supplied them, but the
  deployment now holds them.

## Personal data map

| Where | What is stored | Why it exists | Access |
|---|---|---|---|
| `users` | name, email, password hash, role, 2FA secret, `last_activity` | Identity and access | Admin manages; user sees self |
| `vendor_users` | name, email, password hash | Vendor portal identity | Vendor sees self; admin manages |
| `trust_center_access_requests` | requester name, email, company, reason, review notes, access token, expiry, access count | Gating protected documents | Admins review; requester holds a magic link |
| `trust_center_documents` / content blocks | Whatever is published, often with names in signatures or contact blocks | Public or gated disclosure | Public or approved requester |
| `survey_answers` / `survey_attachments` | Free text and files describing the vendor | Third-party risk assessment | Internal roles; vendor sees own survey |
| `data_request_responses` / `file_attachments` | Evidence, often screenshots with names, emails, hostnames | Audit evidence | Internal roles; requestee |
| `comments` and mentions | Names, opinions, sometimes personal circumstances | Collaboration | Subscribers and mentioned users |
| `activity_log` | Actor name and email, changed field values | Accountability and forensics | Internal users via the admin activity page |
| AI prompts and responses | Selected record content sent to an external provider | Drafting and gap analysis | Provider, plus whoever can read the record |

If a new field, upload, or integration adds a row to this table, say so in the
pull request and decide the retention before it ships.

## Principles in practice

1. **Minimise at the point of entry.** Collect the field the control needs, not
   the field that might be useful. A "notes" box is not a licence to paste an HR
   case file.
2. **Purpose is recorded.** Personal data in evidence should serve a named
   control or audit item. If it serves no requirement, it is a liability.
3. **Access is the primary privacy control.** Roles, the separate vendor guard,
   private file authorization, and trust center expiry are what keep data from
   spreading. Do not widen access to make a report easier.
4. **Redact before upload.** Prefer a screenshot with names masked, a CSV with
   the personal columns removed, or a summary over the raw export. State what was
   redacted in the evidence description; that note is itself audit evidence.
5. **Retention is a decision, not a default.** Choose a period per data class,
   write it down, and put the deletion step somewhere a person will see it.
6. **Deletion must be real.** Soft deletes, activity log entries, comments,
   exports, and backups all survive a "delete" click. Know which of them you are
   claiming to have removed.

## Trust center disclosure

- Public documents are world-readable. Before publishing, read the document as a
  stranger: signatures, personal mobile numbers, internal email addresses, and
  customer names are the usual leaks.
- Protected documents are gated by an approved request, a signed URL, and an
  expiry (`trust_center.magic_link_expiry_hours`, default 24). Revoking the
  request clears the token. Record the decision in the review notes.
- `access_count` and `last_accessed_at` are the only usage signal. If the
  programme needs per-download accountability, that is a feature request, not an
  assumption.
- NDA acceptance is captured as a boolean with a timestamped status change. It is
  not a signed contract. Do not describe it as one.

## AI features

`AiService` sends the content it is given to the configured provider (OpenAI or
DigitalOcean). Keys are encrypted at rest and quotas are per day, but the egress
is real.

- Do not paste special-category data (health, biometrics, union membership,
  criminal records) into an AI prompt.
- Treat prompts as disclosures: the provider sees them, and prompts may be logged
  by the provider under its own terms.
- Prefer summaries over raw attachments, and never send a full evidence archive.
- If a deployment needs a data processing agreement or a regional endpoint, that
  is an operator decision recorded in the deployment documentation, not a code
  default.

## Exports, backups, and logs

- `DataManager` exports, audit evidence ZIPs, PDF reports, and database backups
  leave the application boundary. They inherit the sensitivity of the records
  they contain and need the same handling statement.
- `activity_log` retains entries for `delete_records_older_than_days` (180 by
  default), but `activity:clean` is **not scheduled** in this repository. Either
  schedule it or document that the table grows indefinitely.
- Log files may contain names and email addresses. Restrict log access as you
  would restrict the database.
- Deactivating a user soft-deletes the row and keeps the name and email for
  history. That is deliberate for attribution. It is not erasure.

## Data-subject requests and erasure (manual runbook)

There is no built-in DSAR or erasure tool. When one arrives:

1. **Identify the person.** Internal user, vendor user, trust center requester,
   survey respondent, or a name mentioned only inside evidence.
2. **Search every surface**: `users`, `vendor_users`,
   `trust_center_access_requests`, `survey_answers`, `data_request_responses`,
   `comments`, `activity_log`, and attachment metadata. File *contents* need a
   separate search.
3. **Separate obligation from convenience.** Audit evidence may be retained under
   a legal or contractual obligation. Record the basis and the scope instead of
   deleting silently.
4. **Redact where deletion would break an audit trail.** Replace the personal
   data, keep the record, and note what was redacted and why.
5. **Delete the rest, including files.** A database row without its object is not
   deletion. Check the private disk and any export that was produced.
6. **Answer in writing** with what was found, what was removed, what was retained,
   and on what basis.

If this runbook becomes routine, the honest answer is that the deployment needs a
privacy module. Do not build a one-off script and call it compliance.

## Review checklist

- [ ] New personal data is minimal, justified, and documented in the data map.
- [ ] Access is role- or record-scoped; no new blanket read path.
- [ ] Evidence guidance followed: no secrets, no unnecessary personal data
      (`docs/grc/evidence-standard.md`).
- [ ] Trust center content checked for names, contact details, and customer data.
- [ ] AI prompts contain no special-category data and no unnecessary attachments.
- [ ] Exports and reports carry the same access checks as the screen they came
      from.
- [ ] Retention and deletion are stated, even if the answer is "manual".
- [ ] Activity log, comments, and soft-deleted rows considered in any erasure.
- [ ] Cross-border or processor implications escalated to the operator, not
      decided in code.

## References

- `docs/grc/evidence-standard.md` — what may be stored as evidence
- `docs/decisions/0002-training-integration-boundary.md` — learner data boundary
- `app/Http/Controllers/TrustCenterController.php` — disclosure and gating
- `app/Models/TrustCenterAccessRequest.php` — expiry, revocation, access count
- `app/Services/Ai/AiService.php` — provider, model, key handling
- `config/activitylog.php` — retention setting
- `SECURITY.md` — reporting channel for a suspected exposure
