# OpenBao secret metadata

OpenBao is the secret store; OpenGRC is the identity register. OpenGRC records a
`secret_reference` and reads **KV v2 metadata** to keep the rotation date honest.
It never reads the secret value.

See ADR 0029 for the boundary. OpenBao is an MPL-2.0, separately deployed secret
manager; it is optional.

## Configure

```dotenv
OPENBAO_URL=https://bao.example.com
OPENBAO_TOKEN=<read-only metadata token>
OPENBAO_KV_MOUNT=secret
OPENBAO_TIMEOUT=15
```

The token is a read-only metadata reader. A minimal policy:

```hcl
path "secret/metadata/opengrc/*" {
  capabilities = ["read", "list"]
}
```

Do not grant `read` on `secret/data/*`, root, or `sudo`. TLS only. The token is
itself a non-human identity: register it in the identity register and rotate it.

## Reference convention

In a non-human identity, set the secret reference to:

```text
openbao://secret/opengrc/wazuh-api
```

- `secret` is the KV mount.
- `opengrc/wazuh-api` is the metadata path.
- `openbao://opengrc/wazuh-api` uses the configured default mount.
- A pasted data path `openbao://secret/data/opengrc/wazuh-api` is accepted; the
  `data/` segment is stripped.

The sync reads:

```http
GET /v1/secret/metadata/opengrc/wazuh-api
X-Vault-Token: <token>
```

It uses the current version's `created_time` as the rotation date. It does not
call `/v1/secret/data/...`.

## Run the sync

```bash
php artisan opengrc:openbao:sync --dry-run
php artisan opengrc:openbao:sync
```

The command updates:

| Field | Source |
|---|---|
| `last_rotated_at` | Current KV version `created_time` |
| `next_rotation_at` | Last rotation + the identity's rotation interval |
| `secret_synced_at` | Sync time |

It does **not** change status, rotate a credential or read a secret. The daily
`opengrc:identities:review` command still marks an identity expired or rotation
due; rotate the credential in OpenBao and the consumer, verify the old credential
is rejected, then record the review.

Schedule the sync if you run OpenBao:

```cron
15 * * * * cd /var/www/opengrc && php artisan opengrc:openbao:sync >> storage/logs/openbao.log 2>&1
```

## Failure handling

| Status | Meaning | Action |
|---|---|---|
| `200` | Metadata returned | Update the rotation date |
| `403` | Token lacks metadata read | Fix the policy; do not broaden to data |
| `404` | Path or mount is wrong | Fix the reference; do not guess |
| `5xx` / network | OpenBao unavailable | Fail; keep the previous date and show `secret_synced_at` as stale |

A failed sync leaves the record unchanged and exits non-zero. A stale sync is not
a rotated credential.

## What this does not do

- No secret value, no `data` path, no dynamic lease credential.
- No automatic rotation or revocation.
- No PKI private-key or transit-key export.
- No OpenBao audit-log ingestion.
- No replacement for the identity register's owner, purpose, scope and review.

## Security notes

- The token is a read-only metadata credential, not a root token.
- Do not put the token in the repository, a ticket or an AI prompt.
- A secret reference is not secret, but it reveals the path and the system; treat
  the register as internal.
- A metadata sync proves a version exists; it does not prove the consumer was
  updated or the old credential was revoked. Check both.
- If OpenBao is unavailable, the register keeps the last known date and marks the
  sync time; do not overwrite it with today's date to make the page green.

## Limits

- KV v2 metadata only in this slice.
- No lease, dynamic-secret, PKI or transit metadata.
- No write-back, rotation or revocation.
- No OpenBao audit stream.
- The sync is a command, not a background service; schedule it or run it on
  change.
