# 0030 — Guidance is a register, not a framework

Status: accepted
Date: 2026-11-20

## Context

Governments and standards bodies publish a steady stream of guidance: the
Canadian Centre for Cyber Security's ITSAP, ITSP and ITSG series; CISA advisories
and tooling guides; the DoD CIO library; NCSC guidance. These documents are
useful, but they are not control catalogues. They recommend practices, explain a
threat, or describe a process.

Treating each publication as a framework would create dozens of overlapping
pseudo-catalogues, duplicate control text, and leave no record of whether the
organisation actually adopted the advice. Copying the text into the repository
adds a licensing and maintenance problem on top.

The GRC question is smaller: which guidance have we reviewed, what did we decide,
which controls does it inform, who owns the decision, and when do we review it
again?

## Decision

1. **One register entry per publication.** A `GuidanceNote` records the publisher,
   reference, version, publication date, URL, content licence, adoption status,
   owner, applicability, summary and review date.
2. **Reference the source; do not copy it.** The URL and reference are the link to
   the authoritative text. A short decision summary is allowed; the document body
   is not mirrored. The content licence is recorded so attribution is explicit.
3. **Adoption is an explicit status.** New, under review, adopted, not applicable
   or superseded. There is no automatic adoption because a document was imported.
4. **Control mapping is curated.** A note can link to the controls it informs. The
   link is a decision, not an import side effect, and it can be many-to-many.
5. **Review is dated.** A note has an owner and a next review date. An overdue
   review is visible in the register; guidance changes and so does the
   organisation.
6. **Guidance is not evidence by itself.** The evidence is the control, the
   implementation, the test and the artifact. A guidance note points to the
   control; it does not prove the control operates.
7. **Guidance does not become a framework.** If a publication contains a real
   control catalogue, it goes through the framework catalogue and provenance
   register instead. A guidance note is not a substitute for an import.
8. **Licensing is part of the record.** Open Government Licence – Canada, CISA
   public-domain material, and other terms are recorded. Mirroring text needs a
   licence review before it merges.

## Consequences

- The stream of Cyber Centre, CISA and DoD documents has one home instead of a
  new feature per PDF.
- The register shows what was adopted and what was ignored. That is often more
  useful to an auditor than the guidance itself.
- The summary field can drift from the source. The URL and version are the
  authority; the summary is a decision aid.
- Control mapping takes judgement. A lazy mapping is worse than no mapping.
- The register does not fetch or watch for new versions. Someone must add the
  revision and review it.

## Alternatives considered

- **Import each guidance document as a framework.** Rejected: it creates
  overlapping pseudo-catalogues and duplicates text that is not a control set.
- **Keep a shared drive of PDFs.** Rejected: no owner, no adoption decision, no
  control link, no review date and no licensing record.
- **Copy the guidance into the repository.** Rejected: licence, attribution,
  maintenance and drift; the source is already authoritative.
- **Ignore guidance until an audit asks.** Rejected: the mapping and the decision
  are exactly what the audit asks for.
- **Use a generic document register.** Rejected: it loses the publisher,
  reference, version, adoption status and control link that make guidance
  actionable.
