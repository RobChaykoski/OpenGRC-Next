# Non-human identities

A non-human identity is a machine credential: a service account, API token, bot
token, integration credential, OAuth client or certificate. It is not a person. It
must not have interactive login, and its secret must live in a secret manager, not
in the GRC database.

The register answers four questions: what machine credentials exist, who owns
them, what can they reach, and when were they last rotated?

See ADR 0027 for the boundary.

## What belongs in the register

| Type | Example |
|---|---|
| Service account | A local account used by a backup agent |
| API token | A Wazuh reader, a PatchMon reader, a CI token |
| Bot token | The OpenMetadata ingestion bot, a chat bot |
| Integration credential | A connector password or client secret |
| Certificate | A TLS certificate or a client certificate |
| OAuth client | A machine-to-machine OAuth application |
| Other | Anything that authenticates without a person |

Do not register a person's account, an interactive administrator or a shared
human login. Those belong in the user and access-review process.

## Required fields

| Field | Why it exists |
|---|---|
| Name | What the credential is called |
| Type | The kind of credential |
| System | The product or service it authenticates to |
| Owner | The person accountable for the next review and rotation |
| Purpose | Why it exists; "integration" is not a purpose |
| Scope | Roles, permissions or resources it can reach |
| Secret reference | The secret-store path, never the secret |
| Rotation interval | How often it should be replaced |
| Last rotated | The last known rotation date |

Optional: environment, next rotation (computed), last used, expiry, next review,
revocation date and reason, notes.

## Lifecycle

| State | Meaning | How it changes |
|---|---|---|
| Active | In use, within its rotation interval | Created or rotated |
| Rotation due | Past the rotation interval | Daily review command |
| Expired | Past its explicit expiry | Daily review command |
| Pending review | Owner or scope needs a decision | Operator |
| Revoked | No longer valid | Operator, with a reason |

A revoked or expired identity stays readable. Deleting the row destroys the
evidence that the credential existed.

## Rotation and expiry

```bash
php artisan opengrc:identities:review --dry-run
php artisan opengrc:identities:review
```

The command marks active identities expired or rotation due and reports those due
within 14 days. It does not rotate or revoke anything.

If the secret lives in OpenBao, refresh the rotation date from KV metadata:

```bash
php artisan opengrc:openbao:sync --dry-run
php artisan opengrc:openbao:sync
```

The sync reads only metadata (`current_version` and its `created_time`). It never
reads the secret value. See `docs/integrations/openbao.md`.

Rotation is a human action in the secret store and the owning system:

1. Create the replacement credential with the same or narrower scope.
2. Update the consumer.
3. Verify the old credential no longer works.
4. Record the rotation date and the change reference.
5. Revoke the old credential.

A rotation with no verification is a hope, not a control.

## Ownership and scope

- Every identity has a named owner. An unowned credential is a finding.
- The scope field records the permissions, not the secret. A broad scope is a
  finding even when the rotation date is current.
- Prefer one credential per consumer and environment. Shared credentials destroy
  attribution and make rotation a coordinated outage.
- A production credential must not be reused in staging or development.
- Record the environment; a token with no environment is a guess.

## Review cadence

| Frequency | Activity |
|---|---|
| Monthly | Review identities due for rotation or expiry in the next 30 days |
| Quarterly | Confirm owners, purposes and scopes; revoke what is unused |
| Annually | Re-baseline rotation intervals and the inventory |
| On change | Register a new credential before it is used; revoke the old one after verification |

## Evidence and audit

The register is metadata evidence. For a control test, pair it with:

- the secret-store version or change record that shows the rotation;
- the ticket or change that approved the new scope;
- the access review that confirmed the owner;
- the decommission record for a revoked credential.

A register row with no rotation evidence proves only that someone typed a date.

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| Tokens never rotate | No owner or no interval | Set an owner and a rotation interval |
| One token shared by several services | Attribution was lost | One credential per consumer; revoke the shared one |
| A token in a spreadsheet or chat | No secret store | Move it to the secret manager; record the reference |
| Rotation done but the old token still works | No verification | Verify, then revoke, then record |
| No production/staging separation | Environment was not recorded | Add the environment and separate the credentials |
| A machine identity used by a person | The boundary was ignored | Disable interactive use; issue a personal account |

## Limits

- No secret storage and no automatic rotation.
- No discovery of unregistered credentials from an IdP or cloud provider yet.
- No impersonation or usage analytics; `last_used_at` is operator-supplied or
  imported.
- No automatic revocation in the owning system.
- A future secret-manager integration can verify rotation and pull last-used data;
  it is a separate decision and a separate credential.
