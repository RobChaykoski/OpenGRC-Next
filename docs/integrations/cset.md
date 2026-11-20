# CSET assessment import

[CSET](https://github.com/cisagov/cset) (Cyber Security Evaluation Tool) is a
CISA/INL assessment tool for standards and maturity models. OpenGRC imports the
**PCII-scrubbed JSON export** and turns CSET observations into findings. It does
not run CSET, store its question bank, or recompute its score.

See ADR 0038 for the boundary.

## Export from CSET

1. Complete the assessment in CSET.
2. Choose the JSON export, not the encrypted `.cset` archive.
3. Enable the option to remove PCII, if your CSET version offers it.
4. Review the file before importing. A comment or observation can still contain
   sensitive detail even when the demographic blocks are removed.

## Import

```bash
# Parse and report without writing
php artisan opengrc:cset:import storage/app/cset-export.json --dry-run

# Import and link to an application or asset
php artisan opengrc:cset:import storage/app/cset-export.json --application=12 --asset=42
```

The command prints the assessment reference and the number of findings created
or updated.

## What is read

| Read | Not read |
|---|---|
| Assessment name, date, GUID | Organisation name, site, facility |
| SAL level | Contacts, names, email, phone |
| Selected standards and maturity models | CIS demographics |
| Requirement and question counts | Question and requirement text |
| Negative answer count | Comments and feedback |
| Observations: id, importance, title, summary, issue, impacts, recommendations | Raw export file contents beyond the import |

The importer normalises camelCase and PascalCase keys, so exports from different
CSET versions with the same field names are accepted.

## Findings

Each unique observation becomes a finding. The dedupe key is:

```text
cset:{assessmentGuid}:{observationId}
```

If an observation has no id, a hash of its title, summary and issue is used.
Re-importing the same export updates the finding and increments `occurrences`;
an accepted finding stays accepted.

Importance maps as follows:

| CSET importance | Severity |
|---|---|
| Very high, critical | Critical |
| High | High |
| Medium, moderate, blank | Medium |
| Low, informational | Low |

Due dates: critical 7 days, high 30, medium 60, low 90 from first sight.

## What it does not do

- No `.cset` archive decryption or zip handling.
- No answer-level findings; negative answers are counted in report metadata.
- No CSET score, maturity level or compliance percentage.
- No automatic control state change.
- No retention of comments, feedback, contacts or demographics.
- No scheduling or re-import; run the command when a new export is available.

## Evidence and privacy

- The report keeps the file SHA-256, CSET metadata and the derived findings.
- The export itself stays in the document store; do not commit it.
- Treat imported findings as internal. Review observations before publishing a
  summary or sending them to an AI prompt.
- A CSET observation is an assessor's statement, not proof. Link it to a control,
  risk or remediation action before calling it managed.
