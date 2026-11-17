---
name: OpenGRC Framework Mapping
description: Use when importing a standard, crosswalking frameworks, or reviewing control mappings - NIST 800-53, ISO 27001, SOC 2, CIS, HIPAA, CMMC. Covers identifiers, bundle structure, crosswalks, deduplication, and how to keep mappings reviewable by an auditor.
---

# OpenGRC Framework Mapping

Mapping a framework is reference-data work with real legal weight. A bad mapping
is worse than no mapping because it creates false confidence during an audit.

## Before importing

1. **Check the licence.** Many standards restrict redistribution. OpenGRC ships
   some public-domain frameworks (NIST, CIS controls) and not others. Do not add a
   bundle of a copyrighted standard.
2. **Use the published identifier scheme exactly.** `AC-2(1)`, `A.5.15`, `CC6.1`,
   `CIS 4.1`. Never renumber, never reorder, never "clean up" punctuation.
3. **Import one framework version at a time.** A standard's revision is part of its
   identity; 800-53 Rev. 5 is not Rev. 4.
4. **Record provenance**: title, version, publisher, retrieval date, URL.

## Bundle shape

A bundle imports a standard and its controls. Keep these rules:

- The `Standard` row is immutable reference data after import.
- Each `Control` carries the framework identifier, the requirement text, and the
  standard it belongs to.
- Parent/child structure (control families, enhancements) is expressed through
  relations, not through string prefixes in the title.
- Do not translate the requirement text at import time. Localisation is a separate
  layer; an auditor must be able to match our text to the published text.

## Crosswalks

A crosswalk maps one framework's control to another's. It is not a merge.

- Store the mapping with both identifiers + both standard versions.
- Mark a mapping as `equivalent`, `partial`, or `related`. "Partial" is the most
  common and the most useful.
- Never delete an implementation because a crosswalk says another control covers
  it. Implementations belong to the organisation, not to the map.
- Re-review crosswalks when either framework publishes a revision.

## Quality checks

Run these before declaring a mapping done:

- [ ] Control count matches the published count (or the documented subset).
- [ ] Every identifier is unique within its standard version.
- [ ] No control text contains import artefacts: HTML scraps, footnotes, page numbers.
- [ ] Family/group structure is preserved.
- [ ] Spot-check ten controls against the source document, including the first and last.
- [ ] Deprecated or withdrawn controls are marked, not silently dropped.
- [ ] Crosswalks cite both versions.

## Common failures

| Symptom | Cause | Fix |
|---|---|---|
| Duplicate controls | Re-import without idempotency | Match on standard + identifier before insert |
| Broken hierarchy | Families inferred from title strings | Model relations explicitly |
| Audit confusion | Local text edited into control descriptions | Keep source text, put local notes on the implementation |
| Coverage gaps | Partial framework imported as if complete | Record the subset and its scope note |
| Stale mapping | Framework revised upstream | Re-import as a new version, re-run crosswalks |

## Training angle

A mapping exercise is a good teaching device because it is verifiable: the learner
either found the right identifier or did not. Pair it with a control-to-evidence
exercise so mapping is not treated as a paperwork game (see
`opengrc-grc-domain-model` and `opengrc-training-scenario-authoring`).
