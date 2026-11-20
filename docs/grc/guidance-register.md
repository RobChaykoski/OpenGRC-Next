# Guidance and advisory register

Governments and standards bodies publish guidance continuously: the Canadian
Centre for Cyber Security's ITSAP, ITSP and ITSG series, CISA advisories, the DoD
CIO library, NCSC guidance and internal security notes. They are not control
frameworks. The register records what you reviewed, what you decided, which
controls it informs, who owns it and when you will review it again.

See ADR 0030 for the boundary.

## What belongs here

A guidance note is one publication:

- a Cyber Centre ITSAP, ITSP or ITSG document;
- a CISA advisory or tooling guide;
- a DoD CIO policy or reference;
- an NCSC or sector guidance note;
- an internal security advisory or architecture note.

Do **not** put a control catalogue here. If the document is a real framework
(NIST, ISO, PCI, CMMC, CIS, CSA CCM), use the framework catalogue and provenance
register instead.

## Fields

| Field | Why it exists |
|---|---|
| Title | What a reader recognises |
| Publisher | Who owns the guidance |
| Reference | The stable publication id, for example `ITSAP.100.49` |
| Version | The revision being reviewed |
| Published date | When the source was issued |
| URL | The authoritative link, not a copied file |
| Content licence | Attribution and reuse terms |
| Status | New, under review, adopted, not applicable or superseded |
| Owner | Who is accountable for the decision and review |
| Applicability | Which teams, systems or obligations it affects |
| Summary | A short decision note, not a copy of the guidance |
| Controls informed | The controls the guidance maps to |
| Reviewed / next review | The decision date and the next check |

## Status

| Status | Meaning |
|---|---|
| New | Captured, not yet read |
| Under review | Someone is assessing applicability |
| Adopted | The organisation has decided to follow it |
| Not applicable | Reviewed and deliberately not adopted |
| Superseded | A newer version or different guidance replaces it |

A `Not applicable` entry is a decision, not a gap. An unowned `New` entry is a
gap.

## Review cadence

| Frequency | Activity |
|---|---|
| Weekly | Triage new entries; assign an owner |
| Monthly | Review adopted guidance with an approaching review date |
| Quarterly | Confirm control mappings and applicability |
| On revision | Add the new version and mark the old one superseded |

The register flags an overdue review. It does not watch the publisher's site for
you; someone must add the revision.

## Control mapping

A guidance note can link to several controls. The link is a curated decision:
"this advisory informs SI-4 and AU-6" is useful; mapping every note to every
control is noise.

When the guidance is adopted, the next step is usually a control implementation,
a policy change or a test. The guidance note points to the control; it is not the
evidence that the control operates.

## Licensing

- **Open Government Licence – Canada** material can be reused with attribution.
  Record the licence and link to the source; do not assume a document can be
  mirrored.
- **CISA** material is generally public domain, but check the specific page and
  any third-party content.
- **DoD CIO** and service publications may carry distribution statements. Record
  the reference and link; do not copy controlled material.
- If a future feature mirrors any text, it needs a licence review and an
  attribution note before it merges.

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| Hundreds of PDFs, no decisions | A shared drive, not a register | One entry per publication, with a status |
| "We follow NIST" but no mapping | Guidance and framework were conflated | Map the guidance to controls; keep the framework separate |
| Old guidance still marked adopted | No review date | Set an owner and a next review |
| Guidance text copied into the repo | Licence and drift risk | Link to the source; keep a short summary |
| A new revision ignored | No revision owner | Add the version and supersede the old entry |

## Limits

- No automatic feed or publisher monitoring.
- No full-text search of the guidance; the source owns the text.
- No automatic control mapping; the link is a decision.
- No document mirroring; the URL and reference are the record.
- No workflow beyond status and review date.
