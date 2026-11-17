---
name: OpenGRC GRC Domain Model
description: Use when modelling, importing, authoring, or reviewing GRC content in OpenGRC - standards, controls, implementations, risks, audits, findings, evidence, policies, assets, vendors. Explains the vocabulary, the relationships, and the invariants that keep a compliance programme coherent.
---

# OpenGRC GRC Domain Model

The product only works if the vocabulary is used the same way everywhere: in the
database, in the UI, in reports, and in training material. This skill defines that
vocabulary and the relationships between the objects.

## The core chain

```
Standard (a published framework: NIST 800-53, ISO 27001, SOC 2, CIS, HIPAA...)
  -> Control (a requirement, written in the standard's language)
       -> Implementation (how *this* organisation meets the control)
            -> Evidence (proof it operates: file, log, screenshot, lab output)
            -> Audit Item (a question asked about the control in a specific audit)
```

Supporting objects:

```
Risk  <-> Control        a control mitigates a risk
Risk  <-> Asset          a risk affects an asset
Risk  <-> Policy         a policy addresses a risk
Implementation <-> Asset, Application, Vendor
Vendor -> Survey -> Response -> Vendor Document
Policy -> Acknowledgement, Exception
Mitigation -> Risk
```

## Definitions that people get wrong

- **Standard** is the source document, imported as a bundle. It is reference data,
  not something the organisation edits.
- **Control** is the requirement. It is inherited from the standard and is the same
  for everyone using that standard version. Do not rewrite control text to match
  local practice.
- **Implementation** is the organisation's answer: what we actually do, who owns
  it, how we test it, and its status. One control can have several implementations
  (different systems, different business units).
- **Evidence** supports an implementation or an audit item. Evidence without a
  link is a file in a folder, not compliance.
- **Audit** is a bounded exercise with a scope and a period. **Audit items** are the
  questions. **Findings** are the gaps. **Remediation** is tracked work, not a note.
- **Asset** is something you can lose: system, service, data store, facility.
  **Application** is software you build or run. They are related, not synonyms.
- **Risk** has a likelihood, an impact, a treatment (accept, mitigate, transfer,
  avoid), and a residual position. A risk with no owner is an observation.

## Invariants

1. **Every implementation points at exactly one control.** If two controls are met
   by the same practice, that is two implementations or a crosswalk, not a merged
   control.
2. **Every piece of evidence is attached to something.** Ungrounded files are
   unretrievable during an audit.
3. **Every risk has an owner and a treatment.** "Open" is a state, not a plan.
4. **Every finding maps to a control or a requirement.** Otherwise it cannot be
   scoped or re-tested.
5. **Status is explicit.** Implemented / partially implemented / planned / not
   applicable, with a reason for not applicable. Silence is not a status.
6. **Do not duplicate framework content to make a report look tidy.** Crosswalk
   mappings are separate from controls.

## Authoring rules

- Write control mappings at the lowest level that is testable. "Complies with
  ISO 27001" is not a mapping; "A.8.8 Technical vulnerability management" is.
- Use the framework's own identifiers (`AC-2`, `A.8.8`, `CC6.1`) verbatim so
  external auditors can follow the trail.
- Keep implementation descriptions operational: what happens, how often, who does
  it, what artifact it produces.
- Evidence should answer "how would a stranger verify this in five minutes?"
- When something is genuinely out of scope, record why. Scope decisions are audit
  evidence too.

## Training angle

When this model is used for teaching, the learner should be able to trace one line
end to end: standard -> control -> implementation -> evidence -> audit item ->
finding -> remediation. If a scenario cannot be traced that way, it is not a GRC
exercise yet; it is a technical exercise with GRC vocabulary attached.
