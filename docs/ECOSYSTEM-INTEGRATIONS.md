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
| OpenEMR | PHI access, retention, and clinical control evidence | Aggregate control evidence with hashes |
| Ralph | Asset inventory and ownership | Asset IDs, owner, criticality, lifecycle state |
| Fleet | Endpoint posture and compliance | Device counts, OS/package state, policy status |
| OpenVAS / Nessus / Nikto / ZAP | Vulnerability and remediation evidence | Finding counts, severities, exception state, patch evidence |
| Wazuh / Suricata / Zeek / Sysmon / Security Onion | Detection and monitoring evidence | Control-linked alert and coverage summaries |
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
