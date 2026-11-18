# Discovery evidence ingestion

OpenDLP-NG (the discovery engine) sends hash-verified evidence snapshots to
OpenGRC. OpenGRC stores them in the append-only evidence ledger and maps them to
assets. Discovery does not score risk, decide applicability, or own frameworks;
that stays in OpenGRC.

See `docs/decisions/0009` for the ledger design and
`OpenDiscoveryEngine/docs/integration/open-grc-api.md` for the producer side.

## Provisioning

Create a dedicated service account, then mint a scoped, client-bound token:

```bash
php artisan opengrc:create-user discovery@example.com 'a-long-password' --role=viewer
php artisan opengrc:token:create discovery@example.com \
  --name=opendlp-ng \
  --client=acme \
  --ability=evidence.write
```

The token is printed once. `--client` is required: it is the tenant binding, and
evidence is deduplicated per `(client, external id)`.

Configure discovery:

```dotenv
DISCOVERY_OPENGRC_URL=https://grc.example.com
DISCOVERY_OPENGRC_TOKEN=<token>
DISCOVERY_OPENGRC_TIMEOUT=15
```

Rotate by creating a new token and replacing the old one. Revoke with:

```php
$user->tokens()->where('name', 'opendlp-ng')->delete();
```

## Request

```http
POST /api/v1/evidence
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json

{
  "id": "b3f1...",
  "sha256": "9f2c...",
  "scanId": "c7a0...",
  "findingCount": 1,
  "capturedAt": "2026-11-18T09:00:00Z",
  "assetIdentifier": "srv-finance-01",
  "externalReferences": {"scan": "c7a0...", "asset": "srv-finance-01"},
  "evidence": {"schema": "opendiscovery.evidence/v1", "findings": []}
}
```

- `sha256` is computed over the canonical JSON of `evidence`: recursively
  key-sorted, compact separators, UTF-8, unescaped slashes.
- `capturedAt` sets `collected_at`; it defaults to ingestion time if omitted.
- `assetIdentifier` is resolved against asset name, hostname, or asset tag. No
  match stores null rather than guessing.
- `externalReferences` is accepted but not persisted yet; it is reserved for the
  data graph.

## Responses

| Status | Meaning |
|---|---|
| `201` | Created; body `{"evidenceId": "...", "status": "accepted"}` |
| `200` | Replay or duplicate; same `evidenceId` as the original |
| `401` | Missing or expired token (JSON, never a redirect) |
| `403` | Token lacks `evidence.write`, is unbound, or a browser session was used |
| `422` | Hash mismatch or invalid payload |

Replays are safe: the write is idempotent on `(client, id)`.

## Verification

```bash
php artisan opengrc:evidence:verify
```

```sql
select id, client_id, external_id, asset_id, payload_sha256, collected_at
from evidence_records
order by id desc
limit 5;
```

The `evidence_records` table is append-only at the model and database layer;
corrections are new snapshots with a new `id`.

## Failure handling (producer)

- `2xx` and `409`: delivered.
- `400/401/403/404/422`: permanent; surface to an operator, do not retry.
- `429`: retry, honouring `Retry-After`.
- `5xx`/network: retry with exponential backoff and jitter.

## Security notes

- Tokens are bearer credentials: TLS only, store as a secret, rotate on a
  schedule and after any suspected exposure.
- A token acts as its user. Use a dedicated service account and do not give it
  admin.
- The endpoint rejects browser-session authentication; evidence must come from a
  machine credential.
- Evidence payloads must not contain raw matched values. Discovery sends
  previews and hashes of matches, not the matched data.
