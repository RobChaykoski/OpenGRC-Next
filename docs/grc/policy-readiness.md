# Policy readiness

A policy programme usually fails in the same place: the documents exist, nobody
implemented them, and nobody tested whether the implementation works. A single
"status: complete" column hides all three problems.

OpenGRC models readiness as **three independent status dimensions** per item:

| Dimension | Question it answers | Values |
|---|---|---|
| Documentation | Is there an approved document? | Not started, draft, in review, approved |
| Implementation | Has it been put in place? | Not planned, planned, in progress, implemented |
| Effectiveness | Has it been shown to work? | Not tested, design effective, needs improvement, operating effective |

An item is only *ready* when the document is approved, the implementation is
implemented, and effectiveness is operating effective. "Design effective" means
the control looks right on paper and has not been observed working; it does not
count as ready.

## The register

`PolicyReadinessAssessment` is one assessment (a point-in-time view, e.g. "FY26
baseline"). `PolicyReadinessItem` is one policy or document inside it. Each item
carries:

- `code`, `category`, `title`, `priority` — identity and grouping
- `framework_applicability`, `nist_csf`, `itsg33`, `cmmc` — where the expectation
  comes from
- `objective`, `expected_evidence` — what "done" means, in words
- `applicability` — `not applicable` exempts the item from maturity scoring
  without deleting it, so the exemption stays auditable
- `owner`, `target_date`, `last_reviewed`, `evidence_location`, `notes`
- `policy_id` — link to the approved `Policy` when one exists

`evidence_location` is deliberately text, not an attachment. It records where the
evidence lives (a path, a ticket, an evidence record). It is a pointer, not proof.

## Importing a checklist

`PolicyReadinessWorkbookImporter` reads the assessment workbook (XLSX) and creates
one assessment plus its items. Run it from the readiness assessment resource's
import action, or call the importer directly. The import is tolerant of extra
columns and blank rows; unknown status text falls back to "not started" rather
than failing the whole row.

The workbook itself is not distributed with OpenGRC. Supply your own copy — it is
usually licensed material from a consultancy or a standards body.

## Using it honestly

1. Baseline once. Do not re-import monthly; that creates duplicate assessments.
2. Set applicability first. Scoring a programme against items that do not apply
   to it makes the maturity number meaningless.
3. Record implementation separately from documentation even when it is
   embarrassing. The gap is the finding.
4. Link each item to a real `Policy` as the document is approved, so the register
   points at the live object instead of a filename.
5. Re-assess on a cadence and keep the old assessment. The trend is the useful
   signal, not the snapshot.

## What this is not

- **Not a policy workflow.** It does not route drafts for review or collect
  acknowledgements; that is the `Policy` model's job.
- **Not evidence.** `evidence_location` is a reference. Attach the artifact to an
  audit item or import it as machine evidence if it needs to be inspectable.
- **Not a control mapping.** The framework columns describe expectation, not a
  crosswalk to imported controls. See the framework mapping guidance for that.
