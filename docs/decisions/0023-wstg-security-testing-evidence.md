# 0023 — WSTG is a test methodology, not a scanner or a copied checklist

Status: accepted
Date: 2026-11-20

## Context

The OWASP Web Security Testing Guide (WSTG) is the common methodology for
testing web applications and web services. Its tests are identified as
`WSTG-<category>-<number>` (for example `WSTG-ATHN-01`), organised into
information gathering, configuration, identity, authentication, authorization,
session management, injection, error handling, cryptography, business logic,
client-side, API and WebAssembly testing.

Three different things are easy to confuse:

- **ASVS** states the security requirements an application should meet.
- **WSTG** describes how to test whether it does.
- **ZAP, Nessus, OpenVAS and similar tools** automate part of the testing.

WSTG is a manual methodology. It is also licensed **CC BY-SA 4.0**, and its test
identifiers can change between versions. Copying the guide into an AGPL
application would mix content licences, require attribution and share-alike
analysis, and create a maintenance burden that has nothing to do with GRC.

A GRC platform does not need the prose. It needs the test plan, the result, the
coverage and the evidence reference, linked to the application and the control.

## Decision

1. **OpenGRC references WSTG, it does not copy it.** Store versioned test IDs,
   category counts and links to the official guide. Do not mirror the guide text
   or the checklist spreadsheets.
2. **OpenGRC does not run web security tests.** Testers use their own tools and
   judgement; OpenGRC stores the assessment summary, the result and the evidence
   references. There is no scanner, crawler or request replayer in the console.
3. **The evidence type is `security-testing`.** A snapshot names the methodology
   and version, the application, the environment, the scope, the tester and the
   date, then reports tests planned, executed, passed, failed and not applicable,
   coverage, failed test IDs and references to the underlying reports.
4. **No request or response content.** Do not send cookies, tokens, credentials,
   personal data, request bodies, response bodies or screenshots with secrets.
   Send counts, IDs, hashes and references to artifacts held in the testing tool.
5. **The producer cannot set the control verdict.** The record is stored as
   Unknown; a validation rule decides whether the control passed. A manual test
   result is a judgement, not an automatic compliance state.
6. **An assessment is point-in-time.** Record the application version, commit or
   release and the environment. Re-test after a material change, not only on an
   annual calendar.
7. **Version the identifiers.** `WSTG-v42-ATHN-01` is unambiguous; an unversioned
   ID is assumed to mean "latest" and will drift. The payload carries
   `methodologyVersion`; producers should use version-qualified IDs when known.
8. **Map deliberately.** WSTG tests support controls such as NIST 800-53 SA-11,
   RA-5 and SI-10, ISO 27001 A.8.25/A.8.26/A.8.28, SOC 2 CC4.1/CC7.1 and PCI DSS
   6.3/11.3, but a test suite is not a control. The control link is a decision,
   not an import side effect.

## Consequences

- The console stays a GRC record; testers keep the tools that actually send the
  requests.
- Coverage becomes visible per application and per category, which is the part
  most assessment reports lose.
- The evidence is only as good as the tester's coverage report. A suite that
  marks everything "not applicable" is a red flag, not a pass.
- Version drift is explicit. A result from WSTG v4.2 is not silently treated as a
  result from a later version.
- The CC BY-SA content stays upstream. The repository links to it; if any text is
  ever mirrored, it needs attribution, a licence note and a share-alike review
  before it merges.
- Scanners still matter, but they are inputs, not the methodology. A clean ZAP
  scan is not a completed WSTG assessment.

## Alternatives considered

- **Embed the WSTG document and checklists.** Rejected: CC BY-SA obligations,
  attribution and share-alike analysis, a large content mirror, and a permanent
  sync problem.
- **Build a web scanner into OpenGRC.** Rejected: execution risk, scope creep,
  and a duplicate of ZAP/Burp/Nessus with a weaker security model.
- **Treat a scanner report as a WSTG assessment.** Rejected: automation covers a
  subset of tests and cannot judge business logic, authorization design or
  process.
- **Use ASVS alone.** Rejected: ASVS says what "good" means; it does not say how
  to verify it. The two belong together.
- **Invent a custom checklist without WSTG IDs.** Rejected: it loses the shared
  vocabulary, the version history and the ability to compare assessments.
- **Copy just the test titles.** Rejected as a default: titles are still upstream
  content, and the IDs plus links carry the useful reference without the licence
  question.
