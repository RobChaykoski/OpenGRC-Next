# Document templates

OpenGRC keeps a library of the reusable documents a programme hands out and
collects: NDAs, scope statements, assessments, and reports. It is a template
register, not a document editor. The original file stays byte-for-byte as
supplied; the text OpenGRC extracts from it is what the product searches,
reviews, and shows.

## What is stored

| Field | Why |
|---|---|
| `source_file` | Path on the private disk. Never web-served directly |
| `source_sha256` | Hash of the original, so a changed file is visible |
| `body_text` | Extracted text, encrypted at rest |
| `placeholders` | Merge fields found in the text, for review |
| `outline` | Headings, so structure can be compared between versions |
| `classification` | Public, internal, confidential, restricted |
| `status` | Draft, in review, approved, retired |
| `approved_at`, `approved_by` | Who accepted the template and when |
| `retention_until` | Blank until a retention decision is recorded |

The original file lives on the `private` disk under `document-templates/`. There
is no public URL; download goes through `DocumentTemplateDownloadController`,
which defers to `DocumentTemplatePolicy`.

## Access

- **Viewer** sees public and internal templates only, read-only.
- **Editor** sees every classification and can create, edit, and approve.
- **Admin** has the same write access as editor, plus deletion.
- **Confidential** and **restricted** templates require a writer. A viewer who
  requests the record URL gets a 403/404 rather than a redacted page.

Classification is the access decision, not the folder. If a template should be
readable by everyone, mark it internal rather than moving the file.

## Importing a batch

Documents come from the operator, not from the repository. Nothing that is
copyrighted or customer-specific is bundled with the application or committed to
git. To bring in a set of templates:

1. Copy the files onto the private disk, e.g.
   `storage/app/private/document-templates/nda.docx`.
2. Write a manifest next to them, `storage/app/private/document-templates/manifest.json`:

   ```json
   {
     "templates": [
       {
         "file": "document-templates/nda.docx",
         "name": "Confidentiality and non-disclosure agreement",
         "code": "TPL-NDA",
         "type": "nda",
         "version": "1.0",
         "classification": "confidential"
       }
     ]
   }
   ```

3. Preview, then import:

   ```bash
   php artisan opengrc:document-templates:import --dry-run
   php artisan opengrc:document-templates:import
   ```

`file` may be a path on the private disk or an absolute path on the host. An
absolute path is copied into `document-templates/` and never linked in place.
The command is idempotent: it matches on `code` (or `name` when no code is set),
skips a file whose SHA-256 has not changed, and updates the record when it has.
A missing file or an unknown `type` is reported and skipped, and the command
exits non-zero.

Supported types: `nda`, `policy`, `scope`, `procedure`, `assessment`, `report`,
`other`. Supported formats: DOCX and standalone HTML. PDF is not parsed; if you
need the text of a PDF, convert it first.

## Editing a template in the app

Uploading a replacement file re-parses on save. The record keeps the previous
approval date but not the previous approval *state*: editing sends the template
back to draft so nobody treats an unapproved change as approved. Approving is a
separate action with a confirmation.

## What this is not

- **Not an authoring tool.** There is no WYSIWYG editor over `body_text`, and
  changing the extracted text does not change the DOCX. Edit the source file.
- **Not a merge engine.** Placeholders are detected, not filled. Generating a
  filled NDA from a template and a vendor record is not implemented.
- **Not e-signature.** Approval records an internal decision. It is not a
  signature and is not evidence of one.
- **Not a document management system.** There is no version history beyond
  `version`, no checkout, and no per-document ACL beyond classification.

## Structured documents need their own model

A template with fields is still prose. Some supplied documents are really data
collection instruments and should not be stored only as a template:

| Document | Better home | State |
|---|---|---|
| Risk assessment questionnaire | Survey (`SurveyTemplate`, `SurveyQuestion`) | Supported: sectioned XLSX import (see below) |
| Penetration test report | Audit with items, or findings per vulnerability | Template only; findings are not imported |
| Infrastructure and security assessment | Asset and control records plus an audit | Template only; score-card tables are not scored |
| DSPM programme metrics | Metrics and evidence records | Template only |
| Transfer risk assessment | Risk register | Template only |

Storing these as templates is honest: the document exists and can be reviewed and
approved. It does not mean the answers are structured, scoreable, or reportable.
Do not describe the library as an assessment engine.

## Questionnaire workbooks

A questionnaire is data, not prose, so it goes into a survey template rather than
the template library. The importer reads a worksheet where section headings and
questions share a column:

```
Internal Programs
Answer
Do you utilize a formal privacy policy to protect PII?   ?Yes
How often is the security policy reviewed?
Perimeter Controls
What perimeter controls are implemented:                 ?Firewall
                                                         ?IDS
```

Import from the survey template list ("Import questionnaire") or from the CLI:

```bash
docker compose exec app php artisan opengrc:survey:import-questionnaire \
  /path/to/questionnaire.xlsx --name="Risk assessment questionnaire" --dry-run
```

Rules the importer applies:

- A **section** is a short line (five words or fewer) with no question mark and no
  option marker. Everything after it belongs to that section.
- A **question** is the first non-option line in a row. Options may follow on new
  lines or tabs in the same cell, marked with `?`, `☐`, `□`, or `✓`.
- One option that is yes/no becomes a boolean question. Two or more become a
  multiple-choice question; a single other option becomes single choice. No
  options becomes long text.
- Questions are imported unordered by risk: `risk_weight` is 0 and nothing is
  required. Score the questionnaire by hand afterwards.
- Re-running against a template that already has questions fails unless you pass
  `--replace`, which deletes the existing questions first.

The importer does not read answer *values* from a filled-in questionnaire, and it
does not infer which questions are mandatory. A workbook with merged cells,
multiple question columns, or a matrix layout is out of scope; restructure it or
import by hand.

## Licensing

Only import documents you have the right to use. Do not commit vendor or
standards-body material to this repository, and do not ship it in an image.
Cloud Security Alliance material (AICM, CAIQ) is a common example: import it
locally for your own programme, never redistribute it. See
`docs/OSS-POLICY.md` and `config/framework_catalog.php` for the provenance
register.
