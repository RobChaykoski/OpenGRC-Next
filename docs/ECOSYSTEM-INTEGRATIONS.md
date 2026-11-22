# Ecosystem integrations for OpenGRC

OpenGRC is the system of record for controls, risks, evidence, and audits. It
should consume evidence from the other projects rather than duplicate their
operational data.

## Recommended integrations

| Source | What OpenGRC consumes | Method |
|---|---|---|
| OpenCyberRange | NICE work-role/task/KSK mappings, course completion, training-to-control evidence | Signed CSV/JSON export or API, keyed by lab slug and framework version |
| OpenDetectionEngine / OpenDLP | Discovery console: scan and finding state, coverage, classification labels, control links | Read-only console API (`/api/v1/console/scans`, `/findings`); findings and metadata only, no raw content |
| OpenDetectionEngine / OpenDLP | Data classification results, discovery coverage, DLP policy evidence | Hash-verified evidence snapshots; aggregate counts and classification labels; no raw content |
| OpenMetadata | Data catalog: dataset inventory, owners, classification tags, lineage context | Read-only catalog API; metadata only, no sample data |
| OpenEMR | PHI access, retention, and clinical control evidence | Aggregate control evidence with hashes |
| Ralph | Asset inventory and ownership | Asset IDs, owner, criticality, lifecycle state |
| Fleet | Endpoint posture and compliance | Device counts, OS/package state, policy status |
| OpenVAS / Nessus / Nikto / ZAP | Vulnerability and remediation evidence | Finding counts, severities, exception state, patch evidence |
| Wazuh / Suricata / Zeek / Sysmon / Security Onion | Detection and monitoring evidence | Control-linked alert and coverage summaries |
| Gophish | Phishing simulation: campaign aggregate counts, rates and dates against an awareness control | Read-only summary API; aggregate only, never target rows, event timelines or submitted data |
| OpenDetectionEngine / SIEM / endpoint engine | Detection rule coverage: YARA/Sigma counts, failing rules, ATT&CK coverage, validation age | Hash-verified `detection-coverage` snapshots; no rule bodies, samples or log lines |
| OpenCyberRange / Apache Caldera / Atomic Red Team | Adversary emulation: techniques attempted, detected, prevented and missed; test counts and IDs | Completed-operation summary over the hash-verified evidence API; no commands, payloads or raw logs |
| OWASP WSTG / ASVS | Web application test coverage, failed test IDs and security requirements | Tester summary over the hash-verified evidence API; no scanner execution, request bodies or copied guide text |
| OSV.dev / deps.dev / OpenSSF Scorecard | Vulnerable dependencies, licenses, project health | CycloneDX SBOM import and OSV queries; package coordinates only, no source or build artifacts |
| OpenBao | Secret metadata: current KV version and rotation date | Read-only KV v2 metadata; no secret values, leases or writes |
| Cyber Centre / CISA / DoD CIO / NCSC | Guidance, advisories and reference architecture | Guidance register: reference, version, adoption, control mapping; link only |
| Authentik | SSO and group/application access | OIDC claims and group membership |
| OpenIAM | Access reviews, certification, segregation of duties | Campaign results and exception records |
| GitLab / GitHub / SonarQube / Dependency-Check / ScanCode | SDLC, code quality, dependency, and licence evidence | CI artifacts or signed manifests |

Wazuh is consumed through the machine-evidence pipeline rather than a custom
export: the connector reads SCA results from the manager API. See
`docs/integrations/wazuh-evidence.md` and ADR 0009.

Discovery follows the same split, with one addition: OpenGRC is the console.
OpenDiscoveryEngine stays headless and GPL-3.0-only; OpenGRC pulls a read-only
mirror of scans and findings, links them to controls, and keeps the review state.
No engine code is linked or copied into OpenGRC. See
`docs/integrations/open-discovery-console.md` and ADR 0018.

OpenMetadata supplies the data-catalog context DSPM needs: which datasets exist,
who owns them, and how they are classified. It is a separately deployed
Apache-2.0 service; OpenGRC reads aggregate metadata and never requests sample
data or writes tags back. See `docs/integrations/openmetadata.md` and ADR 0020.

YARA and Sigma are detection formats, not services. OpenDetectionEngine or the
SIEM owns the rule catalogue and execution; OpenGRC accepts hash-verified
detection-coverage snapshots and lets validation rules decide the control state.
See `docs/integrations/detection-coverage.md` and ADR 0021.

Adversary emulation follows the same rule. Apache Caldera runs in OpenCyberRange,
where the agents, payloads and raw output belong. OpenGRC never holds attack
credentials or starts an operation; it accepts a completed-operation summary and
links the result to a control. See `docs/integrations/adversary-emulation.md` and
ADR 0022.

WSTG is a test methodology, not a product. Testers use their own tools; OpenGRC
accepts a versioned coverage summary and never copies the guide or stores request
and response content. See `docs/integrations/security-testing.md` and ADR 0023.

Gophish runs phishing simulations for the awareness programme. OpenGRC reads one
aggregate measurement per campaign from the summary endpoint and never stores a
target row, an event timeline or a submitted value; whether a click rate passes is
a validation rule against the programme's own target. See
`docs/integrations/gophish-awareness.md` and ADR 0050.

The digital forensics curriculum at `frankwxu/digital-forensics-lab` is reference
material, not an integration: it has no API and its evidence images belong to
third parties. OpenGRC links to a deck and a commit when a scenario uses it, and
maps the case studies to governance records; it never copies decks, images or
case narratives. See `docs/integrations/digital-forensics-lab.md` and ADR 0002.

Supply-chain risk follows the same evidence pattern. A CycloneDX SBOM is imported
as a snapshot; OSV queries turn a vulnerable component into a finding. OpenGRC
does not run a scanner or store source. See `docs/integrations/sbom-osv.md` and
ADR 0028.

Guidance and advisories are a register, not a framework. One row per publication
records the reference, version, adoption decision, owner, review date and mapped
controls. Link and attribute; do not copy the text. See
`docs/grc/guidance-register.md` and ADR 0030.

## Rules

- OpenGRC owns the control ID and the evidence request. Source projects own the
  raw operational record.
- The console is OpenGRC. A source project that needs a governance view exposes a
  machine API; it does not grow a second dashboard for the same state.
- Store evidence references and hashes, not copies of learner, patient, or
  endpoint content.
- Every imported evidence item carries source, timestamp, scope, and a
  confidence/validity note.
- Training-to-control links should use NICE component codes and OCR lab slugs,
  not free text.
- Use the shared role model: privacy office, auditor, mentor, teaching assistant,
  administrator.

## Identity

Authentik is the operational SSO provider. OpenIAM is the governance layer for
access reviews and certification. Keycloak is a lab/reference environment, not
the production SSO for OpenGRC.

See `docs/ECOSYSTEM-TOOLING-MAP.md` and `docs/ROLE-CAPABILITY-MODEL.md`.
