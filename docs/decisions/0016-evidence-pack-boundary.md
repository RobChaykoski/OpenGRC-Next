# 0016 — Evidence packs index evidence; they do not export payloads

Status: accepted
Date: 2026-11-18

## Context

Milestone C starts with audit readiness: an auditor needs to follow a framework
from required outcome to control, implementation, evidence, validation result and
finding. The obvious implementation is "export everything as a ZIP", which would
copy raw evidence payloads, rule definitions and attachments into an unmanaged
file.

That is a privacy and security regression. Evidence payloads can contain host
names, matched values and operational detail. Attachments may contain personal
data. Once exported, none of the ledger's access rules, hashes or withdrawal
records apply.

Auditors also need to trust the pack. A copied payload has no verifiable link to
the ledger unless it carries the same hash.

## Decision

1. `opengrc:evidence:pack` produces a ZIP containing:
   - `manifest.json` with framework, timestamp, counts and an explicit exclusion list;
   - `outcomes.csv` with coverage and derived confidence;
   - `controls.csv`, `implementations.csv`, `evidence.csv`, `validations.csv`,
     `findings.csv`;
   - `README.txt` explaining interpretation and hashes.
2. Evidence rows carry `payload_sha256` and metadata, never the payload itself.
   Validation rows carry status and time, never the rule definition or result.
   Findings carry severity, status and dates, not personal contact details.
3. The pack is framework-scoped and says it is not a compliance certificate.
4. Gaps are reported separately (`opengrc:evidence:gaps`) by reason: no reviewed
   control, no evidence, stale evidence, failing validation. Staleness follows
   the catalogue's frequency rather than one global threshold.
5. The matrix shows stale and failing statuses so the page and the report agree.

## Consequences

- The pack can be shared more freely, because it is an index rather than a copy
  of sensitive data.
- An auditor who needs the raw artifact retrieves it from OpenGRC under access
  control, then verifies it against the hash in the pack.
- The pack is less convenient than a full dump. That is the intended trade-off.
- Exports do not yet include attachments or implementation evidence files; the
  manifest states that rather than silently omitting them.
- The auditor portal (Milestone C.3) must keep the same rule: scoped, read-only,
  logged, and no bulk export by default.
