# 0039 — Regulatory change is a human register, not a feed

Status: accepted
Date: 2026-11-20

## Context

Regulations and standards change. A programme needs to know what changed, whether
it applies, which controls and evidence are affected, who owns the response, and
when the assessment is due. A feed of amendments is useful, but a feed alone does
not answer those questions.

OpenGRC already imports control libraries. It does not subscribe to regulators,
diff published documents, or decide applicability. Building a scraper for every
regulator would be fragile, jurisdiction-specific and difficult to audit.

## Decision

1. **One record per change.** The register captures title, regulator, source link,
   publication and effective dates, status, impact, owner, assessment due date,
   summary, impact assessment, notes and affected frameworks.
2. **The source stays upstream.** Store a summary in the organisation's own words
   and a link. Do not copy the regulation or standard text into the record.
3. **A person decides applicability.** No automatic "this control is affected"
   rule. The owner maps the change to imported standards and then to controls,
   risks, policies or evidence by hand.
4. **Impact is an assessment, not a severity formula.** Low, medium, high and
   critical are human judgements recorded with the rationale.
5. **Status is the review lifecycle.** New, assessing, implemented, no impact,
   closed. A change is not closed because time passed; it is closed because the
   assessment is complete and the response is recorded.
6. **Overdue is visible.** An open change past its assessment due date is
   highlighted and filterable. There is no automatic reminder.
7. **No automatic diff.** OpenGRC does not compare two revisions of a publication
   or claim what changed. The source and the summary are the record.
8. **No regulatory text mirror.** The register is not a second copy of the
   regulator's website and must not become one.

## Consequences

- The programme can show that it watched a change, assessed it and decided what
  to do, which is what an auditor asks for.
- The register is only as good as the person maintaining it. An empty register is
  not evidence that no change occurred.
- Affected frameworks are a link, not a control-level impact analysis. The
  control, risk and evidence links remain manual.
- A source link can rot. Keep the title, regulator and publication date so the
  record is still identifiable if the URL moves.
- No notification means someone must review the register. The overdue filter is a
  prompt, not a reminder service.

## Alternatives considered

- **Scrape every regulator and publish a feed.** Rejected: fragile, expensive and
  does not answer applicability.
- **Store the full text of every change.** Rejected: copyright, maintenance and a
  duplicate of the source.
- **Use the guidance register for changes.** Rejected: guidance adoption and a
  regulatory change have different lifecycles; mixing them loses the assessment
  due date and the affected-framework link.
- **Create findings automatically for every change.** Rejected: not every change
  applies, and a false finding queue trains people to ignore it.
