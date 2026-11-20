# 0021 — YARA and Sigma are detection evidence, not code in the console

Status: accepted
Date: 2026-11-20

## Context

YARA and Sigma are the two common languages of detection engineering. YARA
matches patterns in files and memory; Sigma describes log detections that are
converted into a SIEM query. Neither is a service. YARA is executed by an engine
(the `yara` CLI, Velociraptor, Wazuh, ClamAV, a sandbox); Sigma is executed by a
SIEM after conversion.

A GRC platform needs the answer to "is the monitoring control working?" That
answer comes from rule coverage, validation results and exceptions, not from
storing a rule body or running a scan inside the web application.

Running user-supplied YARA or Sigma in PHP would be an execution and
denial-of-service risk, would duplicate a detection product, and would put
malware signatures and detection logic inside the compliance database.

## Decision

1. **OpenGRC does not execute YARA or Sigma.** No rule engine, no scanner, no
   rule-to-query compiler in the console.
2. **Rule authoring, review and versioning belong in Git and the detection
   platform.** OpenDetectionEngine owns the detection catalogue and the rule
   lifecycle; a SIEM or endpoint engine owns execution. OpenGRC does not become
   a code editor.
3. **OpenGRC owns the compliance record.** It accepts a hash-verified
   `detection-coverage` evidence snapshot through the external evidence API:
   rule counts, failing-rule counts, ATT&CK technique coverage, target backends,
   last validation and its age.
4. **The producer's verdict is not the control verdict.** An external snapshot is
   stored with status Unknown. A validation rule in OpenGRC decides whether the
   control is Effective, Failed or Unknown, so the same evidence can be scored
   differently by framework and by control.
5. **References, not rule bodies.** Phase 1 stores counts and metadata. A later
   rule register may store `rule_id`, `format`, `version`, `repo_url`,
   `commit_sha`, `rule_sha256`, `status`, `control_id` and `last_validated_at`,
   but never the rule text, malware sample or log line.
6. **Validation results are labelled as such.** A rule that fires in a lab, or
   fails to fire, is evidence about a detection, not proof that a control is
   effective in production.
7. **The producer is a machine credential.** The existing `evidence.write` token,
   client binding and hash verification apply. No browser session, no free-text
   evidence type, no write-back to the detection platform.
8. **The endpoint allowlist is explicit.** `data-discovery` and
   `detection-coverage` are accepted in this slice. A new evidence type is a
   contract change, not an arbitrary string.

## Consequences

- Detection engineering stays in the product that can run and test rules; OpenGRC
  stays a compliance console.
- The evidence is only as good as the producer's coverage report. A platform that
  cannot say which rules are active cannot prove a monitoring control.
- A count of rules is not effectiveness. Coverage, failed rules, validation age
  and exceptions must be read together.
- The ingestion path is already hardened: hashed payload, scoped token, client
  isolation, append-only ledger and replay safety.
- Rule drift between Git, the detection platform and the SIEM remains an
  operational problem. OpenGRC can show the gap; it cannot close it.

## Alternatives considered

- **Build a YARA/Sigma editor and scanner into OpenGRC.** Rejected: execution
  risk, code-review and CI requirements, and a second detection product with its
  own permissions and release cadence.
- **Store the rule bodies as evidence.** Rejected: they are code, reveal detection
  logic, and change far more often than a compliance record should.
- **Accept a free-text evidence type from producers.** Rejected: it creates an
  uncontrolled vocabulary and makes control mapping unreliable.
- **Treat rule count as a passing control.** Rejected: a large rule set with
  failing rules and no recent validation is not effective monitoring.
- **Put the rule catalogue only in OpenGRC.** Rejected: Git already provides
  review, tests, history and rollback. A database copy would drift immediately.
