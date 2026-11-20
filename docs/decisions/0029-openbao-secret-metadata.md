# 0029 — OpenBao owns the secret; OpenGRC owns the identity register

Status: accepted
Date: 2026-11-20

## Context

The non-human identity register records service accounts, API tokens, bot tokens
and certificates, but it deliberately does not store the secret value. That leaves
a gap: how does the register know a credential was actually rotated?

OpenBao is an MPL-2.0, community-governed secrets manager (a Vault fork). It owns
secret storage, dynamic credentials, leases, revocation, PKI and transit
encryption. It also exposes KV v2 metadata: the current version number and the
creation time of each version, without returning the secret.

That metadata is exactly what the register needs. OpenGRC does not need the
secret; it needs to know when the current version was created.

## Decision

1. **OpenBao (or another secret manager) owns the secret value.** OpenGRC stores a
   `secret_reference` and lifecycle metadata. It never stores a password, token,
   private key or a copy of the secret.
2. **The integration is metadata-only.** OpenGRC reads KV v2 metadata:
   `GET /v1/{mount}/metadata/{path}`. It never calls the `data` path, a lease
   endpoint that returns a credential, a PKI private key or a transit export.
3. **The sync updates dates, not status.** `opengrc:openbao:sync` sets
   `last_rotated_at`, recomputes `next_rotation_at` from the rotation interval and
   records `secret_synced_at`. It does not mark an identity Active or perform a
   rotation.
4. **The OpenBao token is a read-only metadata reader.** Scope it to `read` and
   `list` on the metadata paths the register needs. No root token, no data path,
   no `sudo`. TLS only. The token is itself a non-human identity and belongs in
   the register.
5. **The reference is explicit.** `openbao://<mount>/<path>` is preferred;
   `openbao://<path>` uses the configured default KV mount. A `data/` segment is
   stripped so an operator can paste a data path without breaking the sync.
6. **Failures are visible.** A 403, 404 or malformed metadata response leaves the
   record unchanged, records an error and fails the command. A stale
   `secret_synced_at` is a signal, not a silent success.
7. **Dynamic secrets, leases, PKI and transit are later.** They have different
   metadata and different risks. Do not read a lease credential to "check" a
   dynamic secret.
8. **OpenBao audit logs are not ingested.** Audit devices are valuable, but the
   event volume, actor data and access rules are a separate decision. Start with
   version metadata, not an audit stream.
9. **OpenBao stays a separate deployment.** MPL-2.0, no code linkage, no default
   compose coupling. It is optional; a deployment without it still uses the
   register manually.

## Consequences

- The register can show a rotation date that comes from the secret store, not from
  someone typing a date.
- The sync is only as good as the token scope and the reference convention. A
  token that cannot read metadata fails loudly; a wrong path leaves the record
  unchanged.
- A metadata sync proves a new version exists; it does not prove the consumer was
  updated or the old credential was revoked. Those remain human checks.
- The OpenBao token becomes another credential to rotate. Register it, scope it
  and review it like every other non-human identity.
- The integration does not make OpenBao mandatory. It improves the register for
  teams that already run a secret manager.

## Alternatives considered

- **Store secrets in OpenGRC.** Rejected: the GRC database becomes a high-value
  target, and the secret manager already exists.
- **Proxy secret reads through OpenGRC.** Rejected: it turns the console into a
  credential broker and expands the blast radius.
- **Read the secret `data` path to compare values.** Rejected: the register does
  not need the value, and a token that can read it is far more dangerous.
- **Auto-rotate from OpenGRC.** Rejected: it needs write access to every secret
  engine and every consumer, and a failed rotation can break production.
- **Only manual date entry.** Rejected: the dates drift immediately and the
  register becomes a claim rather than evidence.
