# 0050 — Phishing simulation is aggregate evidence, not a target record

- Status: accepted
- Date: 2026-11-21

## Context

Security awareness controls — NIST 800-53 AT-2, ISO 27001:2022 A.7.2.2,
SOC 2 CC1.4 — need evidence that a programme runs and that it measures
behaviour, not only attendance. Gophish is the common self-hosted tool for that
programme, and its API makes campaign results easy to pull.

The tempting design is to import the campaign object: it already contains every
target's email address, name, position, IP address, latitude and longitude, plus
a timeline of opens, clicks and submitted form data. That would give OpenGRC the
ability to answer "who clicked", and it would put a second copy of employee
personal data — and occasionally captured credentials — into a governance
database with a different audience, retention period and access model than the
awareness programme.

That is a data-protection problem disguised as an integration.

## Decision

OpenGRC consumes **one aggregate measurement per campaign** through
`GET /api/campaigns/{id}/summary`:

1. **Only the summary endpoint.** Counts (total, sent, opened, clicked,
   submitted, reported, errors), the campaign status, and the campaign's dates.
   The connector never requests `/results`, never reads `timeline`, and never
   stores template or landing-page HTML.
2. **No target identity, ever.** No email address, name, position, IP address,
   geolocation, user agent or submitted value enters an evidence payload. The
   payload carries `aggregateOnly: true` so a reviewer can see the intent in the
   record itself.
3. **Campaign-level names only.** Campaign, template, landing-page and group
   names are stored because they identify the programme run. They are chosen by
   the operator, and must describe a programme or a department, not a person.
4. **The connector does not score the programme.** Every record has status
   `unknown`; a validation rule compares the rates to the organisation's own
   target (ADR 0040). A connector constant is not a risk appetite.
5. **Read-only, bounded, operator-configured.** The base URL comes from source
   configuration and must be http/https; the shared `grcReadOnly` client refuses
   anything but GET/HEAD, follows no redirects, and keeps TLS verification on.
   A run reads the newest `max_campaigns` within `lookback_days`.
6. **Measurement identity is honest.** A completed campaign is measured at its
   completion date, so a later run is a duplicate. A running campaign is measured
   at collection time, so its counts keep moving.

Evidence type: `awareness-training`.

## Consequences

- OpenGRC can evidence that a campaign ran, against which population, and with
  what aggregate outcome. It cannot name the individuals who clicked.
- A per-person investigation stays in Gophish, under the awareness owner's access
  control, retention and privacy notice. The two records join on the campaign id,
  not on a person.
- The rates depend on Gophish's counters. `sent` is the denominator, falling back
  to `total`; `null` means "not computable", never zero. A campaign with no
  targets is Unknown, not a perfect score.
- A different simulator, or a provider with no API, can submit the same
  `awareness-training` payload through the hash-verified evidence API. The
  evidence shape is the contract, not the vendor.
- This does not build training records. Course content, lab completion and
  per-learner outcomes remain with OpenCyberRange or the HR learning system
  (ADR 0002). OpenGRC holds the governance evidence, not the learning transcript.
- The campaign list response still contains target rows in memory for the length
  of the request. The connector extracts identifiers and dates and discards the
  rest; it must never be changed to persist the raw response for "debugging",
  because that is exactly the copy this ADR exists to prevent.

## Alternatives considered

- **Import the full campaign object.** Rejected: duplicates employee personal
  data and sometimes captured credentials into a governance database, and creates
  a reporting capability the awareness programme was not designed to support.
- **Import results without emails, keyed by result id.** Rejected: a per-target
  record with a pseudonymous id is still a behavioural record about a person, and
  the join back to an identity is one spreadsheet away.
- **Let the connector mark a campaign effective at a built-in click-rate
  threshold.** Rejected: a universal threshold is not a risk decision, and it
  would hide a weak programme behind a green badge.
- **Run campaigns from OpenGRC.** Rejected: sending phishing mail and holding
  target lists is an operational security function with its own consent,
  authorisation and abuse handling. Gophish already does it.

## References

- `docs/integrations/gophish-awareness.md` — operator runbook and test matrix
- ADR 0040 — DLP and awareness targets are programme targets, not benchmarks
- ADR 0002 — course and lab content belongs to OpenCyberRange
- ADR 0009 — continuous control monitoring and vendor-neutral evidence connectors
- ADR 0017 — connector authentication requests
- NIST SP 800-53 AT-2; ISO/IEC 27001:2022 A.7.2.2; SOC 2 CC1.4
