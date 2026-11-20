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
| OpenDetectionEngine / SIEM / endpoint engine | Detection rule coverage: YARA/Sigma counts, failing rules, ATT&CK coverage, validation age | Hash-verified `detection-coverage` snapshots; no rule bodies, samples or log lines |
| OpenCyberRange / Apache Caldera / Atomic Red Team | Adversary emulation: techniques attempted, detected, prevented and missed; test counts and IDs | Completed-operation summary over the hash-verified evidence API; no commands, payloads or raw logs |
| OWASP WSTG / ASVS | Web application test coverage, failed test IDs and security requirements | Tester summary over the hash-verified evidence API; no scanner execution, request bodies or copied guide text |
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
