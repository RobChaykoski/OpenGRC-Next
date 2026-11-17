# Framework catalog and licensing register

`config/framework_catalog.php` is the machine-readable source of truth for what
ships with OpenGRC, where it came from, and what its licence allows.
`php artisan opengrc:framework-audit` validates imported data against it.

Run it after importing anything:

```bash
php artisan opengrc:framework-audit
php artisan opengrc:framework-audit --strict   # warnings become failures
php artisan opengrc:framework-audit --json     # for CI or dashboards
```

## What ships

| Framework | Code | Authority | Controls | Redistributable |
|---|---|---|---|---|
| CIS Critical Security Controls v8 | `CSCv8` | Center for Internet Security | 18 | Conditional |
| CIS Controls v8 – IG1/IG2/IG3 | `CIS-CSC-IG*` | Center for Internet Security | varies | Conditional |
| NIST SP 800-53 Rev. 5 (Low) | `800-53 (Low)` | NIST | 796 | Yes |
| NIST SP 800-171 Rev. 2 | `800-171r2` | NIST | 118 | Yes |
| NIST SP 800-171 Rev. 3 | `800-171r3` | NIST | 281 | Yes |
| NIST SP 800-218 (SSDF) | `800-218` | NIST | 768 | Yes |
| CMMC 2.0 Level 2 | `CMMC 2.0 - Level 2` | US DoD | 117 | Conditional |
| DFARS 252.204-7012 | `DFARS 252.204` | US DoD | 16 | Yes |
| HIPAA Security Rule | `HIPAA-Security` | HHS | 50 | Yes |
| AICPA Trust Services Criteria 2017 | `TSC2017a` | AICPA | 61 | **Review required** |

"Controls" is the number of rows in the source CSV after header removal. Where a
framework includes enhancements or sub-requirements, they are separate rows, so
the count is larger than the number of top-level requirements.

## Licensing, stated plainly

- **Public domain (NIST, US regulations):** safe to store, display, and export.
- **CIS Controls:** free to use with attribution, but the CIS terms restrict
  redistribution. Shipping the text inside a commercial product needs review.
- **CMMC:** the control set mirrors NIST SP 800-171, but assessment guides are DoD
  material. Do not bundle the guide.
- **AICPA TSC:** the criteria are AICPA copyright. Treat any distribution beyond
  internal use as a legal question, not a formatting one.
- **ISO/IEC 27001 and PCI DSS:** **not shipped as control data.** They appear only
  as bundle metadata referring to a source document you must obtain yourself.

If you fork OpenGRC into a commercial offering, review
`config/framework_catalog.php` before publishing. The `redistributable` field is
the short answer, and the register exists so this question is answered once rather
than in a meeting every quarter.

## Importing

Each framework is a seeder that reads a CSV from `resources/data`. The catalog
command wraps them so you do not need to know seeder class names:

```bash
php artisan opengrc:framework-import --list
php artisan opengrc:framework-import CSCv8
php artisan opengrc:framework-audit --strict
```

Seeders are not idempotent by default: importing the same framework twice creates
duplicate controls. That is why the audit command checks for duplicate identifiers
and why re-imports belong in a fresh environment, not a production one.

To add a framework:

1. Obtain the source data and confirm the licence.
2. Add the CSV to `resources/data`.
3. Add a seeder modelled on `CSCSeeder` (simplest, single standard) or
   `SP800171r3Seeder` (header mapping, larger set).
4. Register it in `config/framework_catalog.php` with licence, provenance, source
   file, and expected count.
5. Run the audit with `--strict` and fix what it reports.

## Bundles

Bundles are metadata records pointing at a source document (`filename`,
`source_url`). The documents themselves are not distributed with OpenGRC, which
keeps copyrighted standards out of the repository. Users supply their own
licensed copies and upload them if they want them attached.

## Provenance for auditors

An auditor may ask where a control's text came from. Answer with:

1. The framework code and version in the standard record.
2. The provenance URL in the register.
3. The source file in `resources/data` and its git history.
4. The audit output showing counts and duplicate status.

That is a defensible chain. "We downloaded it somewhere" is not.
