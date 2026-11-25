# OSFI readiness with OpenGRC

Status: **guidance mapping**. OpenGRC is a system of record for controls, risks,
evidence, approvals and findings. It does not decide whether an institution is a
federally regulated financial institution (FRFI), does not replace OSFI
supervision, and does not provide legal or regulatory advice.

> **OpenGRC does not ship OSFI guidance text.** B-13, E-23 and the I-CRT
> Framework are principles-based documents published by OSFI. Import or map your
> own controls and cite the source; do not copy the guidance into a shipped
> control bundle without checking OSFI's reproduction terms. See
> [ADR 0003](../decisions/0003-framework-provenance.md) for the provenance rule.

## The three instruments

| Instrument | What it is | Who it applies to | Timing |
|---|---|---|---|
| **B-13 Technology and Cyber Risk Management** | Guideline with principles across governance and risk management, technology operations and resilience, and cyber security | All FRFIs, including foreign bank and insurance branches to the extent consistent with Canadian requirements | Dated 31 July 2022 |
| **E-23 Model Risk Management (2027)** | Principles-based guideline for enterprise model risk management across the model lifecycle, explicitly including AI/ML models | All FRFIs, applied proportionally to size, strategy, risk profile and complexity | Published 11 September 2025; **effective 1 May 2027** |
| **I-CRT Framework** | Advisory and OSFI supervisory methodology for intelligence-led cyber resilience testing of critical business functions | Primarily systemically important banks (SIBs) and internationally active insurance groups (IAIGs); other FRFIs case by case | Dated 1 April 2023; three-year supervisory cadence and event-driven triggers |

I-CRT is a supervisory assessment, not a policy instrument that sets standalone
regulatory expectations. B-13 and E-23 are guidelines. The distinction matters:
an I-CRT result is evidence about a supervised test, not a control catalogue.

## B-13: what to hold in OpenGRC

B-13 is organised around three outcomes and seventeen principles. The table maps
the themes, not the paragraph text.

| B-13 theme | Paraphrased expectation | OpenGRC objects |
|---|---|---|
| Governance, accountability and strategy | Senior officers own technology and cyber risk; a documented, measurable strategy and risk appetite exist | `Program` scope, `Risk`, `Policy`, `Control`, `Implementation`, `ApprovalRequest` |
| Risk management framework | A framework identifies, assesses, manages, monitors and reports technology and cyber risk, including exceptions | `Risk`, `Control`, `Implementation`, `PolicyException`, `Finding`, `RemediationTask` |
| Architecture and asset management | Technology assets are inventoried, classified, configured to a baseline, kept current and disposed of safely | `Asset`, `Application`, `SoftwareComponent`, `SbomDocument`, taxonomy, `EvidenceRecord` |
| Projects and SDLC | Projects and system changes are governed; security requirements are embedded through the lifecycle; acquired systems are assessed | `Control`, `Implementation`, `AssessmentReport`, `Finding`, `EvidenceRecord` |
| Change, release and patch management | Changes are documented, tested, approved and traceable; patches are applied on a risk-based timetable | `ControlValidation`, `EvidenceRecord`, `Finding`, `RemediationTask`; link the change ticket in notes |
| Incident and problem management | Incidents are detected, escalated, contained, recovered and reviewed; lessons feed control improvement | `Exercise`, `Finding`, `RemediationTask`; the incident timeline stays in the incident tool |
| Service measurement, capacity and disaster recovery | Services and capacity are monitored; recovery plans and severe-but-plausible scenarios are tested | `Exercise`, `AuditItem`, `EvidenceRecord`, `ControlValidation` |
| Cyber identify and assess | Threat assessment, intelligence-led testing, vulnerability ranking, data classification and threat modelling are performed | `Risk`, `AssessmentReport`, `Finding`, `Control`, `DocumentTemplate` |
| Cyber defend | Layered preventive controls, secure configuration, cryptography, identity and access, DLP and vulnerability remediation | `Control`, `Implementation`, `AccessReview`, `NonHumanIdentity`, DLP records, `Finding`, `RemediationTask` |
| Cyber detect | Central logging, monitoring, alert triage and forensic readiness | `EvidenceRecord`, `ControlValidation`, `Finding`, `Exercise` |
| Cyber respond, recover and learn | Incident response, taxonomy, playbooks, forensics, root cause and lessons learned | `Exercise`, `Finding`, `RemediationTask`, `Risk`; raw incident material stays in the incident tool |

### The B-13 gaps

- **No incident register.** The incident timeline, regulatory notification and
  final report belong in the incident tool. OpenGRC holds the linked finding,
  the after-action record and the control evidence; it does not run the clock.
- **No change-ticket object.** Record the ticket reference in the implementation
  or evidence note and keep the approval and test result in OpenGRC.
- **No configuration-baseline engine.** A connector or external scanner supplies
  drift evidence; OpenGRC stores the validation result and the finding.
- **No business-continuity or DR plan object.** Use an exercise, audit item and
  evidence record; keep the plan itself in the document repository.

## E-23: model risk management

E-23 is wider than the AI model artifact register. It covers every model whose
inherent risk is non-negligible, including vendor and third-party models, and it
expects a model inventory, risk rating, lifecycle governance, independent
review, approval, monitoring and decommissioning. AI/ML models are explicitly in
scope, including explainability, bias, drift and autonomous behaviour.

| E-23 expectation | OpenGRC support | Limit |
|---|---|---|
| MRM framework, risk appetite and governance | `Policy`, `Risk`, `Control`, `ApprovalRequest`, `Program` | The framework itself is authored content; OpenGRC holds the approval and evidence |
| Model identification and inventory | **Gap.** The `ModelArtifact` register records AI artifact files, hashes, provenance, license and an approval decision | It is not a general model inventory: no purpose, business use, model developer/reviewer/approver, dependencies, data sources, approved uses or review dates |
| Model risk rating and risk-based intensity | `Risk` scoring and `Control` ownership can express the surrounding risk | There is no model risk tier, materiality model or rating history; use an external inventory and link the `Risk` |
| Model design, documentation and data provenance | `ModelArtifact` format, per-file hashes, tokenizer and chat-template hashes, source revision, conversion fields | No conceptual-soundness review, model documentation pack, training-data lineage or bias assessment |
| Independent review | `Audit`, `AuditItem`, `ApprovalRequest`, `ControlAttestation` can record a review and its outcome | No model-reviewer role, review scope template or reviewer-independence rule; keep the review pack in a private attachment and record the decision |
| Model approval and limitations | `ApprovalRequest` and `ModelArtifact` approval record a dated decision | No model-risk-rating approval or conditions field; record the conditions in notes and the linked risk |
| Deployment, change control and monitoring | `ControlValidation`, `EvidenceRecord`, `Finding`, `RemediationTask` | No model performance metrics, drift alerts or production-data comparison; import the monitoring summary as evidence |
| Decommission | `ModelArtifact` has a Retired status; a `Finding` or audit item can record the decision | No general model-lifecycle record, retained-model register or downstream dependency check |

### The E-23 gaps

1. **There is no general model inventory.** The AI model artifact register is a
   supply-chain component register, not the E-23 inventory. Do not present it as
   one to an OSFI reviewer.
2. **There is no model risk rating or materiality workflow.** Use an external
   model inventory or a controlled spreadsheet, link a `Risk` record, and record
   the tier and review date in that source.
3. **There is no independent model-review workflow.** Use an audit or an
   approval request, attach the review pack privately, and record the reviewer,
   scope, findings and conditions.
4. **There is no model monitoring or drift engine.** Import aggregate monitoring
   results through the evidence pipeline; do not copy customer data or model
   inputs into OpenGRC.
5. **E-23 is effective on 1 May 2027.** A programme that starts now has time to
   build the inventory and review cycle before the effective date; do not
   backfill a rating history that never existed.

## I-CRT: supervisory testing evidence

The I-CRT Framework describes an OSFI-led, intelligence-led red-team assessment
of critical business functions. It is controlled and confidential, and the
outputs include a threat-intelligence report, red-team test reports for the FRFI
and OSFI, a remediation plan and an OSFI supervisory letter.

| I-CRT phase | What OpenGRC can hold | What it must not hold |
|---|---|---|
| Scope and critical business functions | `Program` scope, `Asset`/`Application` criticality, `Risk` links | Raw system diagrams or target details in broadly visible fields |
| Threat intelligence | A private evidence record with the report hash, source, date and TLP handling note | The report body itself in a general evidence payload |
| Execution and red-team reports | A restricted `Audit` or `Exercise` with private attachments and a hashed summary | Raw TTPs, credentials, packet captures or exploited-system details in shared records |
| Findings and remediation | `Finding`, `RemediationTask`, `Risk`, approval requests for treatment | Supervisory-letter text copied into a public trust center or broad export |
| Closure and supervisory letter | The external letter reference, closure evidence and an audit trail | A claim that OpenGRC issued, received or interpreted the OSFI letter |

### The I-CRT gaps

- **No TLP or clearance compartments.** Use role-based access and separate
  private storage; OpenGRC does not enforce OSFI's "need to know" labels.
- **No dedicated I-CRT object.** A restricted audit or exercise is the closest
  fit; keep the phase structure in the audit items and attachments.
- **No supervisory-letter register.** Record the reference and closure evidence
  in the audit or finding; do not paste the letter into a shared field.
- **No automatic three-year cadence.** Schedule the supervisory cycle
  externally and record the dates and scope in OpenGRC.
- **No red-team tooling.** OpenCyberRange or a contracted provider runs the
  exercise; OpenGRC holds the governance consequence, not the attack.

## Evidence mapping

| OSFI question | OpenGRC answer |
|---|---|
| What is in scope, and which critical business functions? | `Program` scope, `Asset`, `Application`, `Risk` |
| Who owns the control or model? | `Implementation` owner, `ModelArtifact` owner (artifacts only), approval record |
| Is the measure documented? | Policy, control description, implementation, model artifact record |
| Is it operating? | Dated audit item, validation run, evidence record, exercise |
| Is it effective? | Outcome, finding count, remediation status, review decision |
| Was it independently tested? | Audit, exercise, auditor portal; I-CRT is an external supervisory test |
| Did management approve it? | Approval request, control attestation, dated model approval |
| Can a stranger inspect it? | Evidence ledger entry, hashed artifact, restricted auditor link |

## Minimum setup sequence

1. Record the FRFI scope: entity type, jurisdiction, critical business
   functions, and the systems and third parties that support them.
2. Map B-13's themes to the controls you already operate. One control per theme
   is enough if it is owned, evidenced and tested; do not create seventeen empty
   controls because the guideline has seventeen principles.
3. For E-23, choose the model inventory now. If OpenGRC is not the inventory,
   keep the authoritative list externally and link each model to a `Risk` and an
   `ApprovalRequest`; use `ModelArtifact` only for the AI artifact file.
4. Record the model risk tier, approved use, limitations, reviewer, next review
   date and decommission decision in that inventory, not in free-text notes.
5. For I-CRT, create a restricted audit or exercise, attach only hashed
   summaries and private files, and link findings and remediation tasks.
6. Run the incident and disaster-recovery tabletops that B-13 expects; attach the
   after-action record as evidence.
7. Review the programme quarterly: stale evidence, overdue findings, expiring
   exceptions, model reviews approaching their date, and the next I-CRT trigger.

## What not to claim

- A control mapped to a B-13 principle is not proof that the principle is
  satisfied. The evidence and the test decide.
- A `ModelArtifact` approval is not an E-23 model approval. It covers the
  artifact file, not the model's conceptual soundness or fitness for use.
- An I-CRT audit record is not an OSFI supervisory finding or a substitute for
  the supervisory letter.
- OpenGRC does not classify the institution, decide whether a model is
  non-negligible, or set the I-CRT cadence.

## Related

- [NIS2 readiness](nis2-readiness.md)
- [Framework catalog and licensing](framework-catalog.md)
- [Risk methodology](risk-methodology.md)
- [Findings and remediation](findings-and-remediation.md)
- [Evidence standard](evidence-standard.md)
- [Exercises and tabletops](exercises.md)
- [AI model artifacts](ai-model-artifacts.md)
- [Control attestations](control-attestations.md)
- [Approval workflows](approval-workflows.md)

## Sources

- OSFI, "Technology and Cyber Risk Management" (B-13), 31 July 2022:
  https://www.osfi-bsif.gc.ca/en/guidance/guidance-library/technology-cyber-risk-management
- OSFI, "Guideline E-23 - Model Risk Management (2027)", 11 September 2025,
  effective 1 May 2027:
  https://www.osfi-bsif.gc.ca/en/guidance/guidance-library/guideline-e-23-model-risk-management-2027
- OSFI, "Intelligence-led Cyber Resilience Testing (I-CRT) Framework",
  1 April 2023:
  https://www.osfi-bsif.gc.ca/en/guidance/guidance-library/osfis-intelligence-led-cyber-resilience-testing-crt-framework
