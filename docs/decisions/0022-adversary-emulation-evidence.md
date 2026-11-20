# 0022 — Adversary emulation runs in the range; OpenGRC consumes the summary

Status: accepted
Date: 2026-11-20

## Context

Adversary emulation platforms such as Apache Caldera execute real techniques
against real hosts. They hold agent payloads, credentials and command-and-control
channels, and their operation logs can contain command output, file paths and
target detail. A GRC web application is the wrong place for any of that.

Atomic Red Team is a library of technique tests rather than a service. It is
executed by `Invoke-AtomicRedTeam`, an executor, or Caldera's Atomic plugin. The
same boundary applies: the range runs the tests; the summary crosses to OpenGRC.

Yet purple-team results are exactly the evidence a control-testing programme
needs: which ATT&CK techniques were attempted, which succeeded, which were
detected, and which were prevented. That is evidence about a control, not a
reason to put an attack platform behind the console.

The boundary is already established for training and discovery (ADR 0002, ADR
0018). Adversary emulation extends it: OpenCyberRange owns the lab and the attack
runtime; OpenGRC owns the compliance record.

## Decision

1. **Caldera (or any emulation engine) runs in OpenCyberRange, not OpenGRC.**
   The range owns agents, payloads, planners, target networks, operation state
   and raw results.
2. **OpenGRC never holds attack-platform credentials and never starts, pauses,
   cancels or re-runs an operation.** There is no write client and no direct
   Caldera connector. A compromised GRC session must not become a launch pad.
3. **OpenCyberRange exports a completed-operation summary.** OpenGRC accepts it
   through the hash-verified external evidence API as
   `evidenceType: adversary-emulation`, with a client-bound `evidence.write`
   token.
4. **Aggregates only.** The summary carries technique counts, coverage
   percentages, the range and operation identifiers, the adversary profile,
   the test library and version, test IDs where the library reports tests, and
   asset IDs. It never carries commands, payloads, credentials, agent tokens,
   raw logs, file contents or target hostnames.
5. **The producer cannot set the control verdict.** The record is stored as
   Unknown; a validation rule decides whether the detection or prevention
   objective passed. A lab result is not a production compliance verdict.
6. **Authorization and scope come first.** Operations run only in an isolated
   range, only against approved targets, with written authorization and a
   defined stop condition. The export names the operation and range so the
   evidence is reproducible and attributable.
7. **The summary is retained; the raw operation stays in the range.** Floating
   artifacts, command output and agent data do not enter the GRC database, its
   backups or its search index.
8. **Evidence is about the test, not the estate.** A clean operation proves the
   tested controls on the tested targets at that time. It does not prove that
   production is safe, and a failed technique does not by itself make a control
   non-compliant.

## Consequences

- The GRC console cannot launch an attack. That is the point: less capability,
  much less blast radius.
- Visibility is delayed and aggregate. Real-time operation dashboards stay in
  the range; OpenGRC shows the completed result and its age.
- The range must produce a stable export. If it cannot say which techniques were
  detected, the evidence is a training record, not a detection measurement.
- Detection counts depend on sensor truth. If Wazuh or the SIEM missed an alert,
  the summary will claim a gap; that is a finding, not an integration bug.
- Authorization and privacy review become part of the exercise, not an
  afterthought. Targets are asset IDs, not people.
- A future direct integration, if ever added, must be read-only, range-owned and
  limited to result summaries. It must not become an orchestration API.

## Alternatives considered

- **Add a Caldera connector to OpenGRC.** Rejected: it puts attack-platform
  credentials and write methods behind the compliance console, and the API is an
  offensive control plane rather than a read-only evidence source.
- **Embed Caldera in OpenGRC.** Rejected: code and licence coupling, a Python
  runtime inside the PHP release, and an unacceptable blast radius.
- **Store raw operation data as evidence.** Rejected: command output, payloads
  and target detail are exactly what must not enter the GRC database.
- **Skip the evidence and rely on the range's own report.** Rejected: the control
  link, the exception and the audit trail belong with the other controls, not in
  a lab report that is not retained or reviewed.
- **Use a vulnerability scanner as a substitute.** Rejected: a scanner finds
  known weaknesses; adversary emulation tests whether the detection and response
  controls actually work against behaviour.
