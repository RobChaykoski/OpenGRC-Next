# 0027 — Non-human identities are a register, not a secret store

Status: accepted
Date: 2026-11-20

## Context

Every integration creates a machine credential: a service account, an API token,
a bot token, an integration password, an OAuth client or a certificate. These
identities are easy to create and hard to govern. They are often unowned, shared
between people, scoped wider than the integration needs, and never rotated. A
departing employee leaves with the knowledge of where the token lives, not with
the token itself.

OpenGRC already depends on several non-human identities: the Wazuh and PatchMon
connectors, the OpenMetadata bot token, the discovery console reader key, the
evidence ingestion tokens, and the application's own API tokens. The platform
should model what it asks customers to govern.

A secret manager or identity provider owns the credential value. OpenGRC must not
become a second secret store, and it must not log, export or prompt an AI model
with a token.

## Decision

1. **The register stores metadata, not the secret.** It holds a secret-store
   reference, the owner, purpose, scope, rotation interval and lifecycle dates.
   It never stores the password, token value, private key or a hash of the secret.
2. **A non-human identity is not a user.** It is a separate model with no
   interactive login, no password reset and no session. Reusing the user table
   would blur the access review and the attribution of actions.
3. **The minimum record is name, type, system and owner.** Purpose, scope,
   environment, rotation interval, last rotation, expiry and review date are part
   of the record because they are the controls.
4. **Rotation and expiry are surfaced, not automated.** A daily command marks an
   active identity Expired or RotationDue. Rotation and revocation stay human
   actions in the secret store and the owning system. Automatic rotation is a
   later integration with a secret manager, not a status change.
5. **Revocation is a status with a date and a reason.** The record stays readable;
   deleting it destroys the evidence that the credential ever existed.
6. **Least privilege is recorded, not assumed.** The scope field states the roles
   or resources the identity can reach. A broad scope is a finding even if the
   rotation date is current.
7. **Ownership is a person.** An unowned machine identity is a finding. The owner
   is accountable for the next review and the next rotation, not just the first
   creation.
8. **The register covers OpenGRC's own integrations first.** The Wazuh reader,
   PatchMon reader, OpenMetadata bot, discovery console key and API tokens are
   the first entries a deployment should create.

## Consequences

- A reviewer can answer "what machine credentials exist, who owns them, what can
  they reach, and when were they last rotated?" without opening a secret store.
- The register is a metadata view. It does not prove the secret was rotated; the
  change record or secret-store version is the evidence.
- The daily command can mark a credential RotationDue without rotating it. That is
  intentional: the status is an alert, not an action.
- Legacy shared credentials are exposed as a problem, not hidden. The fix is a
  named owner and a new credential, not a note in a spreadsheet.
- Secret managers and IdPs remain the source of truth for the credential. The
  register links to them; it does not replace them.

## Alternatives considered

- **Store the secrets in OpenGRC.** Rejected: it turns the GRC database into a
  high-value target, expands the backup and log exposure, and duplicates a secret
  manager.
- **Reuse the User model.** Rejected: machine identities have no interactive
  login, no MFA and different review rules; a shared table would corrupt both.
- **Rely only on the IdP or IGA.** Rejected as the sole source: many integrations
  use local tokens, certificates and legacy service accounts that never appear in
  an identity provider.
- **A spreadsheet with a rotation column.** Rejected: no ownership, no evidence,
  no consistent review and no alert.
- **Auto-rotate from OpenGRC.** Rejected for this slice: it requires write access
  to every secret store and every target system, and a failed rotation can break
  production. Surface the due date first; automate with a deliberate integration
  later.
