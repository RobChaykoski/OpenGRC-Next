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
| NIST SP 800-53 Rev. 5 – full catalogue | `NIST-800-53r5` | NIST | 1014 | Yes |
| NIST SP 800-53B – Low | `NIST-800-53B-Low` | NIST | 149 | Yes |
| NIST SP 800-53B – Moderate | `NIST-800-53B-Moderate` | NIST | 287 | Yes |
| NIST SP 800-53B – High | `NIST-800-53B-High` | NIST | 370 | Yes |
| NIST SP 800-53B – Privacy | `NIST-800-53B-Privacy` | NIST | 96 | Yes |
| NIST SP 800-53 Rev. 5 – Low (legacy bundle) | `800-53 (Low)` | NIST | 186 | Yes |
| NIST SP 800-171 Rev. 2 | `800-171r2` | NIST | 110 | Yes |
| NIST SP 800-171 Rev. 3 | `800-171r3` | NIST | 97 | Yes |
| NIST SP 800-172 Rev. 3 | `NIST-800-172r3` | NIST | 103 | Yes |
| NIST SP 800-218 (SSDF) | `800-218` | NIST | 42 | Yes |
| NIST Cybersecurity Framework 2.0 | `NIST-CSF-2.0` | NIST | 128 | Yes |
| NIST AI Risk Management Framework 1.0 | `NIST-AI-RMF-1.0` | NIST | 91 | Yes |
| CMMC 2.0 Level 2 | `CMMC 2.0 - Level 2` | US DoD | 110 | Conditional |
| DFARS 252.204-7012 | `DFARS 252.204` | US DoD | 16 | Yes |
| HIPAA Security Rule | `HIPAA-Security` | HHS | 49 | Yes |
| AICPA Trust Services Criteria 2017 | `TSC2017a` | AICPA | 61 | **Review required** |

"Controls" is the number of rows in the source CSV after header removal. Where a
framework includes enhancements or sub-requirements, they are separate rows, so
the count is larger than the number of top-level requirements.

NIST frameworks are generated from the official OSCAL content, not hand-typed:

```bash
php scripts/oscal-to-csv.php catalog.json resources/data/nist-sp800-53r5.csv
```

The register records the source and version. Regenerating writes a reviewable
diff; it does not silently change control text.

## Licensing, stated plainly

- **NIST:** works of US Government employees are not subject to copyright in the
  United States (17 U.S.C. § 105). NIST grants a royalty-free right to publish
  and adapt the work elsewhere and asks for attribution. Attribute NIST, do not
  imply endorsement, and do not use the NIST logo. A minority of NIST documents
  quote third-party material; use the OSCAL content, which is control text.
- **CIS Controls:** free to use with attribution, but the CIS terms restrict
  redistribution. Shipping the text inside a commercial product needs review.
- **CMMC:** the control set mirrors NIST SP 800-171, but assessment guides are DoD
  material. Do not bundle the guide.
- **AICPA TSC:** the criteria are AICPA copyright. Treat any distribution beyond
  internal use as a legal question, not a formatting one.
- **ISO/IEC 27001 and PCI DSS:** **not shipped as control data.** They appear only
  as bundle metadata referring to a source document you must obtain yourself.

## Canadian and provincial instruments

These come up in Canadian programmes. The rule is the same: bundle only what the
licence allows, and link to the rest.

| Instrument | Publisher | Licence position | What to do |
|---|---|---|---|
| ITSG-33 (Overview, Annex 3A, Annex 4A) | CSE / Canadian Centre for Cyber Security | Crown work listed on the Open Government Portal under the Open Government Licence – Canada. Superseded on 31 March 2026 by ITSP.10.033 | Usable with attribution, but start new work on ITSP.10.033; keep ITSG-33 only for existing profiles |
| ITSP.10.033, Security and privacy controls and assurance activities catalogue | Canadian Centre for Cyber Security | Open Government Licence – Canada; aligned to NIST SP 800-53 Rev. 5, adds PM, PT and SR families | The current Canadian catalogue. Import locally once you have a machine-readable copy; not bundled yet |
| Directive on Automated Decision-Making, including Appendix C | Treasury Board of Canada Secretariat | Government of Canada work; OGL-Canada applies unless the page says otherwise | Import as a control set for AI governance. The Appendix C requirements vary by impact level, so model levels, not one flat list |
| Algorithmic Impact Assessment (AIA) | Treasury Board of Canada Secretariat | The AIA states it is published for sharing and reuse under an open licence | Best modelled as a survey/questionnaire (65 risk + 41 mitigation questions), not as controls |
| AI Strategy for the Federal Public Service 2025–2027 (BT48-55/2025E-PDF) | Treasury Board of Canada Secretariat | Government of Canada publication; OGL-Canada applies unless noted | A strategy, not a control catalogue. Record it as context or policy evidence; do not present it as controls |
| GO-ITS 25.0 General Security Requirements | Government of Ontario | Publicly posted, but no open licence statement; site carries © King's Printer for Ontario. It also derives from ISO/IEC 27002, which is commercial | Do not bundle. Import only with written permission, or map your own controls to it |
| GO-ITS 36 (ESACM), GO-ITS 44 (ITSM terminology) | Government of Ontario | Same position as GO-ITS 25.0 | Link and map; do not redistribute the text |
| Cyber Security Maturity Assessment (CMA) tool | Cyber Security Ontario | Assessment tool behind a login; © King's Printer for Ontario | Not redistributable. Use it as an external assessment and record the result as evidence |
| OSFI B-13 Technology and Cyber Risk Management | Office of the Superintendent of Financial Institutions | Government of Canada guidance; OSFI reproduction terms apply | Link and map the principles to your controls. Do not bundle the guideline text |
| OSFI E-23 Model Risk Management (2027) | Office of the Superintendent of Financial Institutions | Government of Canada guidance; effective 1 May 2027 | Link and map. OpenGRC does not provide a general model inventory; see `docs/grc/osfi-readiness.md` |
| OSFI Intelligence-led Cyber Resilience Testing (I-CRT) Framework | Office of the Superintendent of Financial Institutions | Advisory and supervisory methodology, not a control catalogue | Keep the restricted I-CRT evidence in a controlled audit or exercise; do not bundle the framework |

"Open Government Licence – Canada" requires attribution and does not grant
trademark rights or cover personal information. It is a real permission, not a
public-domain dedication.

## Importing

Each framework is a seeder or an importer that reads a CSV from `resources/data`.
The catalog command wraps them so you do not need to know class names:

```bash
php artisan opengrc:framework-import --list
php artisan opengrc:framework-import NIST-800-53B-Moderate
php artisan opengrc:framework-audit --strict
```

NIST entries use `OscalCsvImporter`, which is idempotent: an existing standard
with controls is left alone, so `--all` cannot duplicate a catalogue or detach
implementations from their controls. The older seeders are not idempotent; run
them once, in a fresh environment, and never in production.

To add a framework:

1. Obtain the source data and confirm the licence.
2. Add the CSV to `resources/data`. For NIST, generate it with
   `scripts/oscal-to-csv.php` and record the OSCAL version.
3. Register it in `config/framework_catalog.php` with licence, provenance, source
   file, importer or seeder, and expected count.
4. Run the audit with `--strict` and fix what it reports.

## Bundles

Bundles are provenance metadata for standards OpenGRC does **not** ship. Each
entry records the authority, licence, redistributable status, provenance URL and
a note. There is no `data` file and no seeder: the text is not ours to
redistribute. Users obtain a licensed copy and import it locally if they want it
in the database.

| Bundle | Authority | Licence position | What to do |
|---|---|---|---|
| `ISO-27001` | ISO/IEC | Commercial, purchase required | Buy ISO/IEC 27001:2022; map your controls or import a licensed copy |
| `ISO-27002` | ISO/IEC | Commercial, purchase required | Reference the guidance; do not copy the control text |
| `ISO-27701` | ISO/IEC | Commercial, purchase required | Privacy extension; purchase required |
| `ISO-42001` | ISO/IEC | Commercial, purchase required | AI management system; pair with your AI policy |
| `PCI-DSS` | PCI SSC | Free download, redistribution restricted | Download v4.0.1; import a licensed copy locally |
| `CSA-CCM` | Cloud Security Alliance | Free download with registration, redistribution restricted | Download CCM v4.0.1; use the CSA mapping, do not bundle the text |
| `CMMC-2-L1` | US DoD | Derived from FAR 52.204-21 | Fifteen practices; already present in the CMMC Level 2 CSV |
| `CMMC-2-L3` | US DoD | Derived from NIST SP 800-172 | Selected enhanced requirements; the assessment guide is DoD material |
| `SOC2-Type2` | AICPA | AICPA copyright | The report is client-confidential; the criteria are not redistributable |

Some bundle keys (`CSCv8`, `800-171`, `800-53-Low`, `HIPAA-Security`) also exist
as importable standards. They remain in the bundle register so a programme can
reference the source document without implying that a second copy is shipped.

A bundle is not an importable framework. If a future change adds a data file and
a seeder, it belongs under `standards`, not `bundles`, and the licence must be
reviewed first.

## Provenance for auditors

An auditor may ask where a control's text came from. Answer with:

1. The framework code and version in the standard record.
2. The provenance URL in the register.
3. The source file in `resources/data` and its git history.
4. The audit output showing counts and duplicate status.

That is a defensible chain. "We downloaded it somewhere" is not.
