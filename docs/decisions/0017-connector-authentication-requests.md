# 0017 — Authentication requests for evidence connectors

Status: accepted
Date: 2026-11-20

## Context

ADR 0009 makes the evidence pipeline read-only: a connector may read a vendor API
but must never change it, and `Http::grcReadOnly()` refuses every method except
`GET` and `HEAD`. Wazuh's manager API does not fit that shape. It has no
long-lived API key: every caller must `POST /security/user/authenticate` with
HTTP Basic credentials to mint a JWT that expires after 900 seconds. Without that
POST, no read endpoint can be called at all.

The alternatives are worse:

- **Disable the read-only guard for connectors that need auth.** That removes the
  protection from every request in the connector, not just the token request.
- **Require an operator-supplied JWT.** Wazuh tokens expire in 15 minutes, so the
  source would stop working before an operator could finish configuring it.
- **Use the Wazuh indexer instead.** SCA results are manager-side, and the
  indexer has a different authentication model and schema.

## Decision

Add one narrow macro, `Http::grcAuthenticate($baseUrl, $path, $caBundle = null)`.
It permits exactly one `POST`, to the path the connector passes as a class
constant. The path never comes from source configuration, so an operator cannot
turn it into a general write client. Redirects stay disabled and TLS verification
stays on; a CA bundle may replace the system trust store but can never set
`verify` to `false`.

`WazuhConnector` uses it only to mint a token, then calls every data endpoint
through `Http::grcReadOnly()` with the bearer token. Tokens are held in memory
for the run and are never written to an evidence payload or a log.

## Consequences

- Wazuh can be supported without weakening the read-only rule for data
  collection. The exception is one method on one path.
- A malicious or mistaken base URL receives a credential-bearing POST. That is
  the same trust boundary as the GETs that follow it, but it means the source
  URL must be an operator-controlled Wazuh manager over TLS, not an arbitrary
  URL supplied by a user.
- A future connector that needs a different write verb must add its own ADR and
  macro. It must not expand this one.
- The token lifetime is shorter than a large collection run. The connector
  re-authenticates once on `401` and retries, so a 900-second token does not turn
  the tail of a large fleet into `Unknown`.

## Alternatives considered

- **Allow `POST` on `grcReadOnly()` behind a flag.** Rejected: the flag would be
  one line away from every connector, and the next author would use it for a
  genuine write.
- **Store a refresh token in the source configuration.** Rejected: Wazuh does not
  offer one, and a stored long-lived secret is a larger liability than minting a
  15-minute token per run.
