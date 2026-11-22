# Gophish awareness evidence

This is the operator runbook for the Gophish connector, plus the reasons it reads
so little.

Gophish is a self-hosted, MIT-licensed phishing simulation service. It owns the
campaign: target groups, templates, sending, tracking and the raw event timeline.
OpenGRC consumes **one aggregate measurement per campaign** as evidence for the
awareness control that owns it.

See ADR 0050 for the decision and `docs/integrations/wazuh-evidence.md` for the
connector pattern this follows.

## What OpenGRC stores

One evidence record per campaign, from
`GET /api/campaigns/{id}/summary`. Nothing else is requested.

| Field | Example | Meaning |
|---|---|---|
| `external_id` | `gophish:campaign:7` | Stable per campaign |
| `evidence_type` | `awareness-training` | Used by validation rules |
| `asset` | `null` | A campaign is not an asset |
| `collected_at` | campaign `completed_date`, or now while it runs | When the measurement applies |
| `status` | `unknown` always | A rule decides pass or fail; see below |

Payload shape:

```json
{
  "source": "gophish",
  "campaign": {
    "id": 7,
    "name": "Q4 finance awareness",
    "status": "Completed",
    "template": "Payroll update",
    "landingPage": "Credential capture page",
    "groups": ["Finance"]
  },
  "stats": {
    "total": 120,
    "sent": 118,
    "opened": 60,
    "clicked": 14,
    "submittedData": 3,
    "emailReported": 21,
    "error": 2
  },
  "rates": {
    "openedPct": 50.8,
    "clickedPct": 11.9,
    "submittedPct": 2.5,
    "reportedPct": 17.8
  },
  "period": {
    "launchDate": "2026-10-01T13:00:00Z",
    "sendByDate": null,
    "completedDate": "2026-10-09T16:00:00Z"
  },
  "campaignAgeDays": 51,
  "aggregateOnly": true,
  "capturedAt": "2026-10-09T16:00:00Z"
}
```

What is **never** read into a payload:

- target email addresses, first/last names, positions, IP addresses, latitude and
  longitude (`results`)
- per-target event entries, browser user agents and submitted credentials
  (`timeline`, `details`)
- template HTML or landing-page HTML
- the SMTP sending profile and its headers

Rates use `sent` as the denominator, falling back to `total` when nothing has
been sent. `null` means "cannot be computed", never zero.

## Why the status is Unknown

A click rate is a measurement, not a verdict. The organisation decides what an
acceptable rate is, and that decision belongs in a validation rule, not in
connector code. Evidence type `awareness-training` is written for exactly this.

Create **Admin → System → Validation rules** against the awareness control
(`AT-2` in NIST 800-53, `A.7.2.2` in ISO 27001:2022, `CC1.4` in SOC 2):

```json
{
  "match": { "evidence_type": "awareness-training" },
  "assert": [
    { "path": "period.completedDate", "op": "exists" },
    { "path": "campaignAgeDays", "op": "lt", "value": 366 },
    { "path": "rates.clickedPct", "op": "lte", "value": 10 },
    { "path": "rates.submittedPct", "op": "lte", "value": 2 }
  ]
}
```

Adjust the numbers to the target your programme has actually agreed. A rule with
an invented threshold creates a comfortable false picture, which is the failure
mode ADR 0040 warns about.

## 1. Create a read-only API key

Gophish has one API key per account, and the account must be able to read
campaigns. Use a dedicated account, not the administrator:

1. In Gophish, create a user (for example `opengrc-reader`) with campaign
   visibility limited to what the awareness programme owns.
2. Sign in as that user, open **Settings → Account settings**, and copy the API
   key.
3. Store the key in OpenGRC only. Rotate it when the account changes owners.

The connector sends the key in the `Authorization` header, which is what Gophish
expects. It does not use a bearer token.

## 2. Configure the source

**Admin → System → Evidence sources → Create evidence source**

| Field | Value |
|---|---|
| Name | `Gophish awareness` |
| Type | `Gophish` |
| Enabled | on |
| API base URL | `https://gophish.example:3333` (the web root; `/api` is optional) |
| API token | the key from step 1; it is write-only in the UI |
| Primary control | `AT-2`, `A.7.2.2`, `CC1.4`, or whichever code your catalogue uses |
| Campaign lookback (days) | `180` |
| Max campaigns | `25` |
| CA bundle | path to a PEM if Gophish uses a private CA; TLS verification is never disabled |
| Timeout | `30` for a first run |

The base URL is operator-supplied. The connector refuses anything that is not
`http`/`https`, follows no redirects, and only issues GET requests.

## 3. Run a collection

```bash
php artisan opengrc:evidence:collect --source=<id> --dry-run
php artisan opengrc:evidence:collect --source=<id>
php artisan opengrc:evidence:validate
```

A campaign still running is measured with the collection time, so its counts keep
moving. A completed campaign is measured once at its completion date: collecting
again is a duplicate, not a second data point.

## 4. Read the record

**Admin → System → Evidence records**, filter by the source. A record shows the
aggregate counts, the rates, the campaign dates, and the payload hash. The
evidence browser is an internal system: campaign names and group names can still
describe an internal reorganisation, even without a person's identity.

## 5. Test matrix

| Case | How to produce it | Expected |
|---|---|---|
| Completed campaign | Run a small campaign to completion | One record, `completed_date` as `collected_at`, rates present |
| Running campaign | Collect while a campaign is in flight | New record per run, counts change |
| No campaigns | Empty Gophish instance | One `gophish:campaigns` record, reason `no_campaigns` |
| Summary unavailable | Revoke the key mid-run, or stop Gophish | `unknown`, reason `summary_unavailable`, other campaigns continue |
| Summary without stats | Point at an old Gophish build | `unknown`, reason `summary_incomplete` (never zero clicks) |
| Old campaign | Launch date older than the lookback | Skipped |
| Large estate | Hundreds of campaigns | Newest `max_campaigns` only |
| Wrong key | Change the token | Job fails with `401`; no evidence written |
| Self-signed TLS | Default Gophish certificate | Job fails with a certificate error; fix the certificate or set a CA bundle |
| Target identities | Inspect the stored payload | No email, name, IP, user agent or credential appears |

## 6. Privacy notes

- Gophish holds personal data by design: who was targeted, who clicked, and
  sometimes what they typed into a landing page. That data stays in Gophish,
  under Gophish's access control and retention.
- OpenGRC records a campaign-level aggregate. It cannot answer "who clicked",
  and it is not meant to. If an investigation needs that, it belongs in Gophish
  with the awareness owner, not in a governance system of record.
- Do not enable credential capture "because the evidence is only counts". The
  connector cannot see submitted values, but the campaign still collected them.
- Campaign and group names are stored. Use names that describe a programme or a
  department, not a person.
- The API key is encrypted at rest, never returned to the browser, and never
  written to a payload or a log.

## 7. Limits

Not implemented in this slice:

- Programmes that use a different simulator (KnowBe4, Proofpoint, GoPhish forks
  with a different API). They can submit the same `awareness-training` payload
  through the hash-verified evidence API instead of a connector.
- Per-user assignment or completion records. Training completion belongs to
  OpenCyberRange or the HR learning system (ADR 0002).
- Automatic campaign creation. OpenGRC does not start a phishing campaign; it
  reads one.
- Trend analysis across campaigns. The latest record per campaign is the unit;
  a trend report is a read model that does not exist yet.
- Evidence for report-and-delete programmes beyond `emailReported`. Report
  timing and the "reported within an hour" measure are not exposed by the Gophish
  summary endpoint.
