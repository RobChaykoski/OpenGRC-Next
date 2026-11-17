# Platform roadmap: the nine pillars

This is a sequencing map, not a promise of dates. Each pillar that changes the
data model or authorization needs its own ADR before implementation. The rule is
the same throughout: build the record and the evidence trail first, then the
dashboards on top of it.

| Pillar | Current state | Next step | Boundary |
|---|---|---|---|
| Multi-tenancy | Single tenant; MSSP deployments use separate instances | ADR for tenant key, global scopes, storage isolation, and per-tenant settings | Do not fake isolation with a nullable `tenant_id` |
| Strong RBAC/ABAC | Three roles (ADR 0007); per-record policies for six models | ADR to replace or extend the three roles with scoped roles and attribute rules | Keep policies explainable; a matrix nobody maintains is not security |
| Immutable audit logs | Spatie activity log; editable table | Apply the evidence-ledger pattern (hash chain, append-only trigger) to audit events | Do not mix operational logs with the audit trail |
| Evidence chain of custody | Implemented for audit attachments, survey attachments, vendor documents, policy documents | Extend to the remaining evidence surfaces; add scheduled verification | No delete; withdraw with a reason; supersede with new evidence |
| Regulatory control mapping | Importable standards, provenance register, manual crosswalks | Add a first-class crosswalk object and coverage reporting | Do not copy licensed framework text |
| Scenario injection engine | Not implemented | Define a scenario object (lab refs, required GRC state, acceptance checks) and inject state into a cohort instance | Labs run in OpenCyberRange; OpenGRC holds the governance state |
| Healthcare compliance packs | General frameworks only | Author PHIPA/PIPEDA/HIPAA-oriented packs from public sources, with provenance | Content licensing and clinical review before shipping |
| Security analytics dashboards | Basic widgets and exports | Read models over risk, evidence, audit, and access events | A dashboard that shows records the viewer cannot open is a leak |
| Training and labs | Training design and skills exist | Scenario contracts, rubrics, and grading from OpenGRC state | Course content and lab runtime belong to OpenCyberRange (ADR 0002) |

## Sequencing

1. **Record integrity** — evidence ledger, migration checksums, immutable audit
   trail. Without this, the rest is a dashboard over unverifiable data.
2. **Scope and access** — tenant model, then scoped roles/attributes. Both are
   cross-cutting and should not be mixed with feature work.
3. **Regulatory content** — crosswalks and healthcare packs, reviewed for
   licensing and clinical accuracy.
4. **Scenarios and analytics** — scenario injection, then dashboards and cohort
   metrics over a stable record model.
5. **Labs** — authored and run in OpenCyberRange, referenced from scenarios.

## Differentiation features

These are the gaps most open-source GRC tools leave open. They are ordered by how
much evidence they produce for the learner, not by how easy they are to draw.

| Feature | Current state | What to build |
|---|---|---|
| Control testing | Test procedure, effectiveness and audit items exist; no scheduled re-test | `last_tested_at`, `next_review_at`, reviewer, result, stale-control report; a test is a record, not a status field |
| Exception management | `PolicyException` exists; no general exception object | Exception with owner, scope, justification, expiry, approval, renewal and revocation; link to risk and control |
| Risk treatment plans | Mitigations with strategy and residual scoring | Explicit plan: treatment, actions, owner, due date, cost, acceptance, evidence, review |
| Policy attestations | Not implemented, despite the capability map implying otherwise | Per-user attestation with policy version, timestamp, IP, hash of the version read; overdue report |
| Regulatory mapping | Manual crosswalks | Crosswalk object with direction, confidence, rationale, reviewer; coverage report across frameworks |
| Healthcare packs | HIPAA Security Rule only | PIPEDA and PHIPA-oriented control sets authored from public sources, with clinical/privacy review and provenance |

Framework licensing is a constraint, not a detail. ISO 27001/27701 and PCI-DSS
text is copyrighted; ship identifiers and mappings only if the licence allows it,
never the control text. PIPEDA/PHIPA are public law, but a usable control set is
authored content and needs review.

## Canadian regulatory context

- **PIPEDA** — consent, access/correction, breach reporting, retention.
- **Quebec Law 25** — consent, transparency, privacy officer, breach register.
- **PHIPA (Ontario) and provincial health statutes** — circle of care, lockbox,
  access/correction, breach reporting, retention.
- **PCMLTFA** — AML/KYC program, records retention, beneficial ownership.
- **OSFI** — governance, cyber-resilience, third-party risk.
- **HIPAA** — applies only when handling US PHI as a covered entity or business
  associate; do not assume it applies to Canadian patients.

The evidence ledger supports the demonstrable part of these obligations. It does
not implement consent management, lockbox, or a breach register; those need
workflow objects and legal review.
