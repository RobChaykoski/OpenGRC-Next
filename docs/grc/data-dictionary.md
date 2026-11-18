# Data dictionary

The objects that carry GRC meaning, what they hold, and how they connect. Field
lists are the important ones, not every column. When this document and the schema
disagree, the schema wins; update this page.

## Core chain

| Object | Key fields | Relations | Lifecycle |
|---|---|---|---|
| `Standard` | `name`, `code`, `authority`, `reference_url`, `description`, `status` | has many `Control` | draft → in scope → retired |
| `Control` | `standard_id`, `code`, `title`, `description`, `discussion`, `test`, `type`, `category`, `enforcement`, `effectiveness`, `applicability` | belongs to `Standard`; has many `Implementation`; morph target of `AuditItem` | inherited from framework, not edited |
| `Implementation` | `code`, `title`, `details`, `status`, `effectiveness`, `notes`, `test_procedure`, `implementation_owner_id` | belongs to many `Control`; many `Risk`; many `Audit`; morph `Mitigation`; many `Policy`; many `Asset`, `Application`, `Vendor` | unknown → not implemented → partial → implemented |

Implementation status values: `Implemented`, `Partially Implemented`, `Not
Implemented`, `Unknown`. Effectiveness values: `Effective`, `Partially Effective`,
`Not Effective`, `Not Assessed`.

## Risk

| Object | Key fields | Notes |
|---|---|---|
| `Risk` | `code` (unique), `name`, `description`, `status`, `inherent_likelihood`, `inherent_impact`, `inherent_risk`, `residual_likelihood`, `residual_impact`, `residual_risk`, `action`, `is_active` | 1–5 × 1–5. `action` is the treatment (`Open`, `Avoid`, `Mitigate`, `Transfer`, `Accept`, `Exploited`) |
| `Mitigation` | `mitigatable_type`, `mitigatable_id`, `description`, `date_implemented`, `strategy` | Polymorphic; used for risk and policy remediation actions |

Status values: `Not Assessed`, `In Progress`, `Assessed`, `Closed`.

Derived: score = likelihood × impact; level thresholds are in
`App\Enums\RiskLevel` (1–4 very low, 5–8 low, 9–12 moderate, 13–17 high, 18–25
very high).

## Audit

| Object | Key fields | Notes |
|---|---|---|
| `Audit` | `title`, `description`, `status`, `start_date`, `end_date`, `manager_id`, `program_id` | Has many `AuditItem`, `DataRequest`, attachments; belongs to `Program` |
| `AuditItem` | `audit_id`, `user_id`, `auditable_type`, `auditable_id`, `status`, `effectiveness`, `applicability`, `auditor_notes` | Polymorphic target: a control, implementation, or other auditable object |
| `DataRequest` | `audit_id`, `code`, `description`, status fields | Request for evidence; has responses and policy links |
| `DataRequestResponse` | `data_request_id`, response content, status | Evidence supplied by the auditee |
| `FileAttachment` | `file_name`, `file_path`, `file_size`, `uploaded_at`, `uploaded_by`, `data_request_id`, `audit_id`, `data_request_response_id` | Evidence storage; not yet attachable to implementations |

Audit item status values: `Not Started`, `In Progress`, `Completed`, `Unknown`.
Applicability values: `Applicable`, `Not Applicable`, `Partially Applicable`,
`Unknown`.

## Policy

| Object | Key fields | Notes |
|---|---|---|
| `Policy` | `title`, `code`, document type, effective/retired dates, owner, revision history | Has acknowledgements; many `Control`, `Implementation`, `Risk` |
| `PolicyException` | `policy_id`, `name`, `justification`, `risk_assessment`, `compensating_controls`, `status`, `requested_date`, `effective_date`, `expiration_date`, `requested_by`, `approved_by` | Expiry is the control that makes exceptions safe |

## Third parties

| Object | Key fields | Notes |
|---|---|---|
| `Vendor` | name, status, risk rating, contact fields, `risk_score` | Many `Application`, `VendorDocument`, `Survey`; many `Implementation` |
| `VendorDocument` | vendor, document type, status | Evidence of vendor assurance |
| `VendorUser` | vendor portal user | External access, separate from staff users |
| `SurveyTemplate` / `SurveyQuestion` | template, questions, weights, active flag | Questionnaire definitions |
| `Survey` / `SurveyAnswer` / `SurveyAttachment` | vendor, template, answers, status | Assessment instance and its evidence |

## Inventory and programme
| Object | Key fields | Notes |
|---|---|---|
| `Asset` | name, owner, criticality, exposure, category, location, supplier | Many `Risk`, `Implementation`; taxonomy-driven classification |
| `Application` | name, type, status, owner, vendor | Many `Implementation`; distinct from assets |
| `Program` | `name`, `description`, `program_manager_id`, `last_audit_date`, `scope_status` | Groups audits and risks; the programme container |
| `Bundle` | name, code, version, authority, `filename`, `source_url` | Source-document metadata; documents are not shipped |
| `Certification` | code, name, issuer | Used by the trust center |

## Trust center

`TrustCenterDocument`, `TrustCenterAccessRequest`, `Certification`,
`TrustCenterContentBlock` — public-facing assurance artifacts and the access
requests that gate them. These are evidence-facing but not part of the internal
control chain.

## Platform objects

| Object | Purpose |
|---|---|
| `User` | Staff identity; roles via `spatie/laravel-permission` |
| `Approval` | Generic approval record for workflows |
| `ActivityLog` | Spatie activity log: who changed what, when, with attribute changes |
| `Taxonomy` | Controlled vocabularies for assets and other objects |
| `QueueJob` | Job visibility for the database queue |

## Canonical catalogue

| Object | Key fields | Notes |
|---|---|---|
| `CanonicalControl` | `code` (unique), `name`, `category`, `risk_domain`, `priority`, `evidence_expectation`, `automation_level`, `frequency`, `sources` | One row per outcome, shared by every framework. Seeded from `config/framework_scoping.php` by `opengrc:catalog:sync`; do not hand-edit |
| `CanonicalFramework` | `code` (unique), `name`, `category`, `priority`, regions/industries/labels/drivers, `rationale` | Applicability definition for scoping, not an imported `Standard` |
| `canonical_control_framework` | `canonical_control_id`, `canonical_framework_id` | Framework → required outcome mapping; replaced on every sync |

## Invariants

1. A `Control` belongs to exactly one `Standard`.
2. An `Implementation` links to at least one `Control`; the chain is how coverage is measured.
3. A `Risk` has an owner and a treatment, or it is not assessed.
4. An `AuditItem` names one auditable target and one criterion.
5. A failed `AuditItem` has a linked risk and a mitigation if remediation is expected.
6. A `PolicyException` has an expiry date.
7. Evidence attaches to an audit item, data request, or response — not to a free-floating file.

## Known modelling gaps

See `docs/grc/README.md` for the full list. The two that affect the dictionary:

- No `Finding` object; findings are audit-item states plus mitigations.
- No clause-level crosswalk between imported controls of different standards; the
  canonical catalogue maps outcomes, not clause-to-clause equivalences.
