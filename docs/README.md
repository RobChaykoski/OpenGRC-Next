# OpenGRC documentation index

Product documentation for end users lives at [docs.opengrc.com](https://docs.opengrc.com).
This folder holds the documentation that belongs to the repository: operations,
architecture, integrations, and the decisions behind them.

## Operations

| Document | Purpose |
|---|---|
| [SUPPORTED-VERSIONS.md](SUPPORTED-VERSIONS.md) | Supported baseline, upgrade tiers, known compatibility traps |
| [UPGRADING.md](UPGRADING.md) | Upgrade, verification, and rollback runbook |
| [../DEVELOPMENT.md](../DEVELOPMENT.md) | Local development setup, including Windows |

## Architecture and decisions

| Document | Purpose |
|---|---|
| [decisions/](decisions/README.md) | Accepted architecture decisions (ADRs) |
| [decisions/0001-version-policy.md](decisions/0001-version-policy.md) | Why the baseline is pinned and tiered |
| [decisions/0002-training-integration-boundary.md](decisions/0002-training-integration-boundary.md) | OpenGRC and OpenCyberRange: system of record vs lab runtime |
| [decisions/0008-evidence-ledger-and-migration-integrity.md](decisions/0008-evidence-ledger-and-migration-integrity.md) | Append-only evidence chain of custody and migration checksums |
| [decisions/0009-continuous-control-monitoring.md](decisions/0009-continuous-control-monitoring.md) | Vendor-neutral evidence connectors and validation rules |
| [decisions/0017-connector-authentication-requests.md](decisions/0017-connector-authentication-requests.md) | The one POST exception for connector authentication |
| [decisions/0018-single-console-discovery-integration.md](decisions/0018-single-console-discovery-integration.md) | OpenGRC fronts the discovery engine; no code merge |
| [decisions/0019-forensic-evidence-handling.md](decisions/0019-forensic-evidence-handling.md) | Authorized, append-only forensic evidence handling |
| [decisions/0020-openmetadata-metadata-source.md](decisions/0020-openmetadata-metadata-source.md) | OpenMetadata as a read-only catalog source, not a second console |
| [decisions/0021-detection-rules-as-evidence.md](decisions/0021-detection-rules-as-evidence.md) | YARA and Sigma are detection evidence, not code in the console |
| [decisions/0022-adversary-emulation-evidence.md](decisions/0022-adversary-emulation-evidence.md) | Adversary emulation runs in the range; OpenGRC consumes the summary |
| [decisions/0023-wstg-security-testing-evidence.md](decisions/0023-wstg-security-testing-evidence.md) | WSTG is a test methodology; reference IDs, do not copy the guide |
| [decisions/0024-exception-scope-and-expiry.md](decisions/0024-exception-scope-and-expiry.md) | Exceptions are scoped, approved and expire by default |
| [decisions/0025-assurance-report-register.md](decisions/0025-assurance-report-register.md) | Assurance reports are a register, not a control catalogue |
| [decisions/0026-privacy-request-register.md](decisions/0026-privacy-request-register.md) | Privacy requests are regime-aware register entries, not audit requests |
| [decisions/0027-non-human-identity-register.md](decisions/0027-non-human-identity-register.md) | Non-human identities are a register, not a secret store |
| [decisions/0028-sbom-and-osv-supply-chain-evidence.md](decisions/0028-sbom-and-osv-supply-chain-evidence.md) | SBOM and OSV are supply-chain evidence, not a scanner |
| [decisions/0029-openbao-secret-metadata.md](decisions/0029-openbao-secret-metadata.md) | OpenBao owns the secret; OpenGRC owns the identity register |
| [FUTURE-ROADMAP.md](FUTURE-ROADMAP.md) | Sequencing for the nine platform pillars |

## Integrations

| Document | Purpose |
|---|---|
| [integrations/opencyberrange-grc-training.md](integrations/opencyberrange-grc-training.md) | Hands-on GRC training design and phased roadmap |
| [integrations/discovery-evidence.md](integrations/discovery-evidence.md) | OpenDLP-NG evidence ingestion: token, payload, replay, retries |
| [integrations/open-discovery-console.md](integrations/open-discovery-console.md) | Read-only OpenDiscoveryEngine mirror in the OpenGRC console |
| [integrations/wazuh-evidence.md](integrations/wazuh-evidence.md) | Wazuh SCA connector, RBAC, TLS, live testing, screenshots |
| [integrations/agent-coverage.md](integrations/agent-coverage.md) | Missed assets, coverage gaps, and agent deployment |
| [integrations/openmetadata.md](integrations/openmetadata.md) | Read-only catalog governance evidence from OpenMetadata |
| [integrations/detection-coverage.md](integrations/detection-coverage.md) | YARA/Sigma coverage and validation evidence from a detection platform |
| [integrations/adversary-emulation.md](integrations/adversary-emulation.md) | Completed range operations as adversary-emulation evidence |
| [integrations/security-testing.md](integrations/security-testing.md) | WSTG test coverage and results without copying the guide |
| [integrations/sbom-osv.md](integrations/sbom-osv.md) | CycloneDX SBOM import and OSV vulnerability evidence |
| [integrations/openbao.md](integrations/openbao.md) | Read-only OpenBao metadata sync for credential rotation dates |
| [integrations/forensic-collectors.md](integrations/forensic-collectors.md) | Linux/Windows native forensic collection matrix |

## GRC practice

| Document | Purpose |
|---|---|
| [grc/README.md](grc/README.md) | Capability map, maturity levels, stated gaps |
| [grc/framework-catalog.md](grc/framework-catalog.md) | Shipped frameworks, licensing, provenance |
| [grc/framework-scoping.md](grc/framework-scoping.md) | Profile → framework/control/evidence scoping |
| [grc/control-catalogue-linking.md](grc/control-catalogue-linking.md) | What a control-to-outcome link means, and why unlinked is a valid state |
| [grc/assurance-matrix.md](grc/assurance-matrix.md) | The framework matrix: statuses, gaps filter, and what the counts exclude |
| [grc/audit-readiness.md](grc/audit-readiness.md) | Evidence packs, gap reports, and what exports deliberately exclude |
| [grc/risk-methodology.md](grc/risk-methodology.md) | 5×5 scoring, treatment, appetite, cadence |
| [grc/evidence-standard.md](grc/evidence-standard.md) | What counts as evidence and how to store it |
| [grc/audit-playbook.md](grc/audit-playbook.md) | Audit lifecycle, findings, closure |
| [grc/program-playbook.md](grc/program-playbook.md) | Roles, cadence, metrics, onboarding |
| [grc/data-dictionary.md](grc/data-dictionary.md) | Objects, fields, relations, invariants |
| [grc/data-posture.md](grc/data-posture.md) | DLP/DSPM dashboard: coverage, classification, exposure, aging, DLP operations |
| [grc/exception-management.md](grc/exception-management.md) | Exception lifecycle, approval, expiry and the global register |
| [grc/assurance-reports.md](grc/assurance-reports.md) | SOC/ISO/PCI/CMMC reports as an inspectable assurance register |
| [grc/privacy-requests.md](grc/privacy-requests.md) | GDPR/CCPA/PIPEDA/FERPA requests, clocks, verification and evidence |
| [grc/non-human-identities.md](grc/non-human-identities.md) | Service accounts, tokens and certificates: ownership, scope, rotation |

## Security

| Document | Purpose |
|---|---|
| [security/container-hardening.md](security/container-hardening.md) | Read-only filesystem, capabilities, WAF, and what is not hardened yet |
| [security/tenant-and-access-roadmap.md](security/tenant-and-access-roadmap.md) | Tenancy, RBAC/ABAC, audit trail, and security analytics sequencing |
| [security/forensics.md](security/forensics.md) | Authorized forensic lifecycle, custody, integrity, retention, and gaps |
| [security/forensic-compliance-mapping.md](security/forensic-compliance-mapping.md) | Forensic controls mapped to the framework catalogue |
| [OSS-POLICY.md](OSS-POLICY.md) | License, dependency allowlist, contribution model |
| [../SECURITY.md](../SECURITY.md) | Vulnerability disclosure |

## Reference

| Document | Purpose |
|---|---|
| [ROADMAP-continuous-assurance.md](ROADMAP-continuous-assurance.md) | Where the assurance product is, and the order to build it in |
| [decisions/0012-canonical-control-catalogue.md](decisions/0012-canonical-control-catalogue.md) | Why the catalogue is config-authoritative and synced into the data graph |
| [API_DOCUMENTATION.md](API_DOCUMENTATION.md) | REST API |
| [NOTIFICATIONS_GUIDE.md](NOTIFICATIONS_GUIDE.md) | Notification behaviour |
| [MediaProxy.md](MediaProxy.md) | Media handling |

## Health check

`php artisan opengrc:doctor` is the executable form of the operations documents.
Add `--json` for monitoring, `--strict` for CI, and `--skip-queue` when the queue
is inspected separately.

`php artisan opengrc:framework-import` imports a control library by catalog code;
`php artisan opengrc:framework-audit` validates imported data against the
provenance register in `config/framework_catalog.php`. `opengrc:catalog:sync`
seeds the canonical outcomes, and `opengrc:catalog:link` applies the curated
control-to-outcome links (see `grc/control-catalogue-linking.md`).
