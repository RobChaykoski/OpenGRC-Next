# OpenMetadata catalog evidence

OpenMetadata is a data catalog: it owns schemas, tables, columns, owners, tags,
domains, lineage and data-quality results. OpenGRC reads a bounded, metadata-only
slice and turns it into **catalog governance evidence** for DSPM and data
governance controls. See ADR 0020 for the decision and the boundary.

This is a **read-only** integration. OpenGRC never writes tags, owners or
glossary terms back to the catalog, and it never reads sample data, profiler
values, table rows or query text.

## Boundary

| OpenGRC owns | OpenMetadata owns |
|---|---|
| Controls, evidence requests, risk, audit and review state | The catalog: data assets, owners, tags, domains, lineage, quality tests |
| Catalog coverage evidence per service | The authoritative metadata and its ingestion |
| The OpenGRC data-classification vocabulary | The catalog's tag and classification vocabulary |
| Control links and evidence history | Metadata freshness and catalog curation |

The two classification vocabularies are **mapped**, not merged. A catalog tag is
counted even when OpenGRC has no code for it; it is never silently guessed.

## What is collected

One evidence record per catalog service (`databaseService`) with:

| Field | Meaning |
|---|---|
| `tableCount` | Tables read in the run |
| `columnCount` | Columns across those tables |
| `ownedTableCount` | Tables with at least one owner |
| `classifiedTableCount` | Tables with a classification tag on the table or any column |
| `unownedTableCount`, `unclassifiedTableCount` | The gaps |
| `ownerCoveragePercent`, `classificationCoveragePercent` | Coverage, rounded to one decimal |
| `classificationTags` | Counts by tag FQN, top 20 |
| `mappedClassifications` | Counts by OpenGRC data-classification code |
| `classificationNamespaces` | The namespaces this run treated as classifications |
| `capturedAt` | UTC collection time |

The evidence type is `catalog-governance`. The external id is
`openmetadata:catalog:<service>`, so a re-run updates the same measurement
identity for the same collection time.

The **Data posture** page shows the latest aggregate per service as ownership
and classification coverage cards, plus the age of the oldest collection. It
reads evidence, not the live API, so the numbers stay honest when collection
stops.

### Status mapping

| Situation | Status |
|---|---|
| Every table has an owner and a classification | `effective` |
| At least one table has no owner or no classification | `failed` |
| The catalog has no tables | `unknown` with `reason: no_tables` |
| The API is unreachable, rejects the token, truncates pagination or disagrees with its own total | Collection fails; no record is written |

A `failed` status is a governance finding, not an integration error. The control
is "the data inventory is owned and classified"; the payload proves which part is
missing. Validation rules can map the same evidence to other frameworks or
require a higher threshold.

## Configure the source

In **Admin → Evidence sources**, create a source of type **OpenMetadata**:

| Field | Value |
|---|---|
| API base URL | `https://openmetadata.example/api` (the `/v1` suffix is optional) |
| Bot token | A read-only OpenMetadata bot JWT |
| CA bundle | Optional PEM path on the OpenGRC host; TLS verification is never disabled |
| Timeout | Seconds, 1–120 |
| Primary control | Optional; a direct control link for display |

The token is stored in the encrypted `configuration` attribute. It is never sent
back to the browser, never written to logs, and a blank field on edit keeps the
stored token. Rotate it by issuing a new bot token, saving it, then revoking the
old one.

### Create a read-only bot token

1. In OpenMetadata, go to **Settings → Bots** as an administrator.
2. Create a bot for OpenGRC (do not reuse the ingestion bot) and give it a
   read-only role on the metadata it needs.
3. Copy the JWT once and store it as a secret in your deployment.
4. Record the owner and a rotation date.

## Global settings

These apply to every OpenMetadata source and live in `.env` or
`config/openmetadata.php`:

```dotenv
# Comma-separated classification root names. A table is classified when it or a
# column carries a tag in one of these namespaces.
OPENMETADATA_CLASSIFICATIONS=PII

# Bound one run. Raise the cap before enabling a very large catalog.
OPENMETADATA_PAGE_SIZE=100
OPENMETADATA_MAX_PAGES=50
```

The tag map is curated in `config/openmetadata.php`:

```php
'tag_map' => [
    'PII.Sensitive' => 'personal_information',
    'PII.NonSensitive' => 'personal_information',
],
```

Add a mapping only after agreeing the target OpenGRC classification code. An
unknown tag is still counted in `classificationTags`; it is simply absent from
`mappedClassifications`.

## Run a collection

```bash
# Read the catalog and report counts without writing evidence
php artisan opengrc:evidence:collect --source=<id> --dry-run

# Write the measurements
php artisan opengrc:evidence:collect --source=<id>

# Keep it fresh
*/60 * * * * cd /var/www/opengrc && php artisan opengrc:evidence:collect --source=<id> >> storage/logs/evidence.log 2>&1
```

A catalog changes more slowly than an endpoint. Hourly or daily is usually
enough; the evidence browser and `opengrc:doctor` show the age of the last
measurement.

## API contract

The connector reads:

```http
GET /v1/tables?fields=columns,tags,owners,service,domains&limit=100&after=<cursor>
Authorization: Bearer <bot-token>
Accept: application/json
```

It follows the `paging.after` cursor to the end. A run that reaches
`OPENMETADATA_MAX_PAGES`, or reads fewer rows than `paging.total`, fails rather
than storing a smaller estate. Unknown fields are ignored so a catalog upgrade
does not need an OpenGRC release.

The connector does **not** call `/v1/classifications`; classification namespaces
are configuration, which keeps the evidence deterministic and avoids depending on
a second permission.

## Failure handling

| Status | Meaning | Action |
|---|---|---|
| `200` | Data returned | Aggregate and store evidence |
| `401` | Token missing, expired or wrong | Fail; issue a new bot token |
| `403` | Bot lacks read access to tables or fields | Fix the bot role; do not widen the token to an admin |
| `429` | Rate limited | Retry later; reduce the page size or frequency |
| `5xx` / network | Catalog unavailable | Fail; keep the previous evidence and treat it as stale |
| Truncated / total mismatch | The run did not read the whole estate | Raise the page cap or fix the API; do not accept partial coverage |

## Security and privacy

- The bot token is a bearer secret with broad read access. Store it as a secret,
  scope the bot, and rotate it. Never paste it into a ticket or screenshot.
- TLS only in production. A private CA is supported through the CA bundle; the
  connector never disables certificate verification.
- Request metadata only. Do not add `sampleData`, `tableProfile` or
  `columnProfile` to the `fields` list; profiling output can contain personal data.
- Catalog metadata (table names, column names, descriptions, tags) can itself be
  personal or confidential. Keep the evidence browser and exports scoped like any
  other evidence.
- OpenGRC stores counts and tag counts, not table FQNs or column values, in this
  slice. A future per-asset mirror needs a privacy review first.
- The OpenMetadata quickstart compose (Elasticsearch, MySQL/PostgreSQL, server,
  ingestion/Airflow) is an evaluation stack. It requires at least 6 GiB RAM and
  4 vCPUs and ships default admin credentials. Do not expose it, and do not add it
  to OpenGRC's default compose. Use SSO, TLS, changed credentials and a separate
  deployment for anything real.

## Limits

Phase 1 does not:

- mirror tables, columns or the lineage graph into OpenGRC;
- collect data-quality results or data contracts;
- write tags, owners or glossary terms back to the catalog;
- support more than one catalog per source or an implicit multi-tenant merge;
- replace OpenDiscoveryEngine or OpenDLP. The catalog says where data is and who
  owns it; the scanner says what is in it; DLP says what may move.

When a catalog gap is accepted, record the exception in OpenGRC and link it to
the control. Do not silence it by removing the classification namespace.
