# NIS2 readiness with OpenGRC

NIS2 is Directive (EU) 2022/2555. It replaced NIS1, entered into force in
January 2023, and had to be transposed by Member States by 17 October 2024.
It applies to essential and important entities in the sectors listed in Annexes
I and II, generally medium-sized and large organisations, with some exceptions.

OpenGRC is a register and evidence system. It does not decide whether an
organisation is in scope, and it does not replace national transposition law,
the national competent authority, or legal advice. What it can do is hold the
scope decision, the control set, the evidence, the tests, and the approvals in
one auditable place.

> **OpenGRC does not ship NIS2 control text.** Transposition adds national
> requirements and identifiers. Import the national framework, or map your
> existing framework to the Article 21 measures below. See
> [ADR 0003](../decisions/0003-framework-provenance.md) for the provenance rule.

## What the directive requires

| Obligation | Article | What an auditor will ask for |
|---|---|---|
| Management-body approval and oversight | 20 | Who approved the risk measures, when, and what did they review? |
| Cybersecurity risk-management measures | 21(1), 21(2)(a)-(j) | A documented, evidenced measure set, reviewed and tested |
| Supply-chain security | 21(2)(d), 21(3) | Supplier inventory, criticality, contractual security, subcontractors |
| Incident notification | 23 | 24-hour early warning, 72-hour notification, one-month final report |
| Registration / jurisdiction | 18, 26 | Which Member State, which authority, which entity class |
| Supervision and enforcement | 32-34 | Evidence that the measures operate, not only that they exist |

The ten Article 21(2) measures are the working control set:

| Measure | OpenGRC object |
|---|---|
| (a) Risk analysis and information-system security policies | `Risk`, `Policy`, `Implementation` |
| (b) Incident handling | **Gap:** no incident register; use an external incident tool and attach the summary as evidence |
| (c) Business continuity, backup, disaster recovery | `Control`, audit items, backup/restore drill evidence |
| (d) Supply-chain security | `Vendor`, subcontractor register, `SbomDocument`, `SoftwareComponent` |
| (e) Security in acquisition, development and maintenance, including vulnerability handling and disclosure | `AssessmentReport` (SARIF), `Finding`, remediation tasks, policy |
| (f) Policies and procedures to assess effectiveness | `Audit`, `ControlValidation`, `ControlValidationRule` |
| (g) Basic cyber hygiene and cybersecurity training | `Exercise`, awareness evidence, policy acknowledgement |
| (h) Cryptography and encryption policies | `Policy`, control evidence, `NonHumanIdentity` rotation records |
| (i) HR security, access control, asset management | `Asset`, `AccessReview`, `NonHumanIdentity`, policy |
| (j) MFA, secured communications, secured emergency communications | `Implementation` evidence, access policy, control tests |

## Evidence mapping

| NIS2 question | OpenGRC answer |
|---|---|
| Is the measure owned? | `Implementation` owner and control owner |
| Is it documented? | Policy, procedure, or control description |
| Is it operating? | Dated audit item, validation run, or attestation |
| Is it effective? | Outcome, finding count, remediation status |
| Is the supply chain covered? | Vendor register plus SBOM and vulnerability findings |
| Did management approve it? | Approval request or control attestation with a date |
| Can a stranger inspect it? | Evidence ledger entry or attached artifact |

## Gaps, stated plainly

1. **No incident register and no regulatory clock.** OpenGRC does not track a
   24-hour early warning, a 72-hour notification, or a one-month final report.
   Keep the incident timeline in the incident tool, attach the notification and
   the final report to the relevant control, and record the CSIRT reference. Do
   not use a task due date as the legal clock.
2. **No national transposition pack.** Member State law adds requirements and
   identifiers. Import the national framework yourself and keep its source and
   version in the framework register.
3. **No automatic entity classification.** Whether an organisation is essential
   or important is a legal and operational decision. Record it in the programme
   scope and the risk assessment, with the reasoning and the date.
4. **No supervisory workflow.** OpenGRC can hold the evidence a supervisor asks
   for; it does not manage a supervisory engagement. The auditor portal is
   scoped to one audit and is a bearer link, not an authority identity.
5. **No NIS2 control text.** Do not invent Article 21 identifiers in a shipped
   CSV. Use the national framework, or a reviewed crosswalk to your existing
   controls, and say which source you used.

## Minimum setup sequence

1. Record the scope decision: entity class, Member State, competent authority,
   and the services in scope.
2. Import one framework you already operate (ISO/IEC 27001, NIST CSF 2.0, or a
   national NIS2 scheme). Do not import five.
3. Create controls for the Article 21(2) measures that are not already covered.
   Link them to the imported framework with a reviewed crosswalk, not a title
   match.
4. Attach the current policy or procedure to each control. An empty control is
   a gap, not a plan.
5. Schedule the tests that prove operation, and record the result. A control
   that has never been tested is level 2, not level 4.
6. Put the suppliers that can affect the in-scope services in the vendor
   register, record their subcontractors, and link the critical ones to the
   supply-chain control.
7. Run a tabletop or exercise for incident handling and attach the after-action
   record. That is the practical evidence for 21(2)(b) until an incident occurs.
8. Review management approval and training evidence before the first
   supervisory conversation, not during it.

## Related

- [Framework catalog and licensing](framework-catalog.md)
- [Risk methodology](risk-methodology.md)
- [Findings and remediation](findings-and-remediation.md)
- [Vendor subcontractors](vendor-subcontractors.md)
- [SBOM and OSV supply-chain evidence](../integrations/sbom-osv.md)
- [Exercises and tabletops](exercises.md)
- [Control attestations](control-attestations.md)
- [Evidence standard](evidence-standard.md)

## Sources

- European Commission, "NIS2 Directive: securing network and information
  systems": https://digital-strategy.ec.europa.eu/en/policies/nis2-directive
- Directive (EU) 2022/2555: https://eur-lex.europa.eu/eli/dir/2022/2555
