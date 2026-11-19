# Ecosystem integrations for OpenGRC

OpenGRC is the system of record for controls, risks, evidence, and audits. It
should consume evidence from the other projects rather than duplicate their
operational data.

## Recommended integrations

| Source | What OpenGRC consumes | Method |
|---|---|---|
| OpenCyberRange | NICE work-role/task/KSK mappings, course completion, training-to-control evidence | Signed CSV/JSON export or API, keyed by lab slug and framework version |
| OpenDetectionEngine / OpenDLP | Data classification results, discovery coverage, DLP policy evidence | Aggregate counts and classification labels; no raw content |
| OpenEMR | PHI access, retention, and clinical control evidence | Aggregate control evidence with hashes |
| Ralph | Asset inventory and ownership | Asset IDs, owner, criticality, lifecycle state |
| Fleet | Endpoint posture and compliance | Device counts, OS/package state, policy status |
| OpenVAS / Nessus / Nikto / ZAP | Vulnerability and remediation evidence | Finding counts, severities, exception state, patch evidence |
| Wazuh / Suricata / Zeek / Sysmon / Security Onion | Detection and monitoring evidence | Control-linked alert and coverage summaries |
| Authentik | SSO and group/application access | OIDC claims and group membership |
| OpenIAM | Access reviews, certification, segregation of duties | Campaign results and exception records |
| GitLab / GitHub / SonarQube / Dependency-Check / ScanCode | SDLC, code quality, dependency, and licence evidence | CI artifacts or signed manifests |

## Rules

- OpenGRC owns the control ID and the evidence request. Source projects own the
  raw operational record.
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
