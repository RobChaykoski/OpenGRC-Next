# Audit readiness: packs and gap reports

Two commands prepare an audit without copying sensitive evidence out of OpenGRC.

## Evidence pack

```bash
php artisan opengrc:evidence:pack cis_v8 --output=storage/app/exports/cis-v8.zip
```

The ZIP contains:

| File | Contents |
|---|---|
| `manifest.json` | Framework, generation time, counts, explicit exclusions |
| `outcomes.csv` | Required outcomes, coverage counts, derived confidence, staleness |
| `controls.csv` | Imported controls linked to each outcome |
| `implementations.csv` | How the organisation implements those controls |
| `evidence.csv` | Evidence metadata and `payload_sha256` |
| `validations.csv` | Validation status and evaluation time |
| `findings.csv` | Findings on the linked controls |
| `README.txt` | How to interpret the pack and verify hashes |

**What is deliberately excluded:** raw evidence payloads, validation rule
definitions and results, attachments and documents, and personal contact
details. The pack is an index with verifiable hashes, not a copy of the evidence
ledger (ADR 0016). Retrieve the artifact from OpenGRC under access control and
verify it against its hash.

The pack is framework-scoped and is not a compliance certificate. It reports what
is linked, what exists, and what is missing.

## Gap report

```bash
php artisan opengrc:evidence:gaps                # summary for every framework
php artisan opengrc:evidence:gaps cis_v8         # detail for one framework
php artisan opengrc:evidence:gaps --json         # machine-readable summary
```

Categories:

| Reason | Meaning |
|---|---|
| No reviewed control | Nobody linked a control to the outcome |
| No evidence | A control is linked, but no evidence or validation exists |
| Stale evidence | The latest evidence or validation is older than the outcome's frequency allows |
| Failing validation | The latest validation result for a linked control failed |

Staleness follows the catalogue instead of a global cutoff:

| Frequency | Evidence considered stale after |
|---|---|
| continuous | 7 days |
| daily | 2 days |
| weekly | 14 days |
| monthly | 62 days |
| quarterly | 125 days |
| annual | 400 days |
| as needed / unset | never automatically |

A recent validation refreshes the outcome even if an older evidence record is
present, because the newest measurement is the one that matters.

The assurance matrix shows the same statuses and its **Gaps only** filter uses the
same report, so the page and the CLI cannot disagree.

## What comes next

C.3 is the auditor portal: scoped to one audit, read-only, logged, and with no
bulk export by default. It must reuse this boundary rather than becoming a second
export path.
