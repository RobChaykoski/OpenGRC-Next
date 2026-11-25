# OpenGRC documentation index

Product documentation for end users lives at [docs.opengrc.com](https://docs.opengrc.com).
This folder holds the documentation that belongs to the repository: operations,
architecture, integrations, and the decisions behind them.

## Operations

| Document | Purpose |
|---|---|
| [SUPPORTED-VERSIONS.md](SUPPORTED-VERSIONS.md) | Supported baseline, upgrade tiers, known compatibility traps |
| [UPGRADING.md](UPGRADING.md) | Upgrade, verification, and rollback runbook |
| [operations/production-promotion.md](operations/production-promotion.md) | Promote a tagged build: backup, MFA, gate, smoke, rollback |
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
| [decisions/0030-guidance-register.md](decisions/0030-guidance-register.md) | Guidance is a register, not a framework |
| [decisions/0031-risk-acceptance.md](decisions/0031-risk-acceptance.md) | Risk acceptance is a dated decision, not a mitigation note |
| [decisions/0032-policy-acknowledgement.md](decisions/0032-policy-acknowledgement.md) | Policy acknowledgement is a versioned attestation, not a click |
| [decisions/0033-access-review-campaign.md](decisions/0033-access-review-campaign.md) | Access review is a campaign snapshot, not a live role list |
| [decisions/0034-exercise-register.md](decisions/0034-exercise-register.md) | Exercises are a register, not a training transcript |
| [decisions/0035-sarif-assessment-import.md](decisions/0035-sarif-assessment-import.md) | Scanner output is findings evidence, not a scanner |
| [decisions/0036-deps-dev-scorecard-enrichment.md](decisions/0036-deps-dev-scorecard-enrichment.md) | deps.dev and Scorecard enrich components; they do not decide compliance |
| [decisions/0037-observable-enrichment-cortex.md](decisions/0037-observable-enrichment-cortex.md) | Observable enrichment is opt-in and privacy-gated |
| [decisions/0038-cset-assessment-import.md](decisions/0038-cset-assessment-import.md) | CSET is an external assessment; import the scrubbed export only |
| [decisions/0039-regulatory-change-register.md](decisions/0039-regulatory-change-register.md) | Regulatory change is a human register, not a feed |
| [decisions/0040-dlp-quality-targets.md](decisions/0040-dlp-quality-targets.md) | DLP false-positive targets are programme targets, not a universal benchmark |
| [decisions/0041-control-attestation.md](decisions/0041-control-attestation.md) | Control owner attestation is a dated statement, not a control test |
| [decisions/0042-control-crosswalk.md](decisions/0042-control-crosswalk.md) | A control crosswalk is a reviewed mapping, not equivalence |
| [decisions/0043-approval-workflow.md](decisions/0043-approval-workflow.md) | Approval requests are an ordered chain, not a workflow engine |
| [decisions/0044-remediation-tasks.md](decisions/0044-remediation-tasks.md) | Remediation tasks are work records, not a project plan |
| [decisions/0057-multi-tenancy.md](decisions/0057-multi-tenancy.md) | Multi-tenancy is a deployment decision, not a nullable column |
| [decisions/0058-network-flow-telemetry-risk-graph.md](decisions/0058-network-flow-telemetry-risk-graph.md) | Flow telemetry feeds a risk graph; OpenGRC stays the GRC authority |
| [decisions/0059-ai-model-artifact-register.md](decisions/0059-ai-model-artifact-register.md) | AI model artifacts are registered by format and provenance, not by weights |
| [decisions/0060-ai-agent-runtime-evidence.md](decisions/0060-ai-agent-runtime-evidence.md) | Agent runtime and sensor evidence is imported, not mediated |
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
| [integrations/assessment-sarif.md](integrations/assessment-sarif.md) | Import SARIF scanner reports as deduplicated findings |
| [integrations/deps-dev.md](integrations/deps-dev.md) | Optional deps.dev license and OpenSSF Scorecard enrichment |
| [integrations/observable-enrichment.md](integrations/observable-enrichment.md) | Cortex/TheHive boundary: opt-in, privacy-gated observable enrichment |
| [integrations/cset.md](integrations/cset.md) | Import a PCII-scrubbed CSET JSON assessment as findings |
| [integrations/forensic-collectors.md](integrations/forensic-collectors.md) | Linux/Windows native forensic collection matrix |
| [integrations/gophish-awareness.md](integrations/gophish-awareness.md) | Aggregate phishing-simulation evidence for awareness controls |
| [integrations/digital-forensics-lab.md](integrations/digital-forensics-lab.md) | External forensics curriculum: provenance, licence limits, scenario mapping |
| [integrations/streaming-evidence.md](integrations/streaming-evidence.md) | Signed push for time-sensitive evidence: stream, cursor, HMAC, responses |
| [integrations/network-flow-telemetry.md](integrations/network-flow-telemetry.md) | OpenCompliance flow export, OpenMetadata enrichment, and the risk-graph projection |
| [integrations/ai-agent-runtime.md](integrations/ai-agent-runtime.md) | Agent gateway and sensor evidence: Prismor receipt import, verification, scope, and excluded sources |

## GRC practice

| Document | Purpose |
|---|---|
| [grc/README.md](grc/README.md) | Capability map, maturity levels, stated gaps |
| [grc/framework-catalog.md](grc/framework-catalog.md) | Shipped frameworks, licensing, provenance |
| [grc/framework-scoping.md](grc/framework-scoping.md) | Profile → framework/control/evidence scoping |
| [grc/nis2-readiness.md](grc/nis2-readiness.md) | NIS2 Article 20/21/23 obligations mapped to OpenGRC objects and stated gaps |
| [grc/ai-model-artifacts.md](grc/ai-model-artifacts.md) | Model format, per-file hashes, provenance, findings and dated approval |
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
| [grc/guidance-register.md](grc/guidance-register.md) | Guidance and advisories: reference, adoption, control mapping, review |
| [grc/risk-acceptance.md](grc/risk-acceptance.md) | Accepting residual risk: acceptor, rationale, expiry and review |
| [grc/policy-acknowledgements.md](grc/policy-acknowledgements.md) | Versioned policy acknowledgement and coverage |
| [grc/access-reviews.md](grc/access-reviews.md) | User access review campaigns, attestation and revocation decisions |
| [grc/access-governance.md](grc/access-governance.md) | Runtime access policy, just-in-time and break-the-glass grants, append-only decisions |
| [grc/exercises.md](grc/exercises.md) | Tabletop, simulation and red-team exercise register |
| [grc/control-testing.md](grc/control-testing.md) | Control test cadence and the due/overdue report |
| [grc/findings-and-remediation.md](grc/findings-and-remediation.md) | Finding lifecycle, risk links and remediation limits |
| [grc/vendor-subcontractors.md](grc/vendor-subcontractors.md) | Fourth-party subcontractor register on a vendor |
| [grc/regulatory-changes.md](grc/regulatory-changes.md) | Regulatory and standard change register with impact assessment |
| [grc/control-attestations.md](grc/control-attestations.md) | Owner attestation requests, statements, declines and expiry |
| [grc/control-crosswalks.md](grc/control-crosswalks.md) | Reviewed control-to-control mappings across frameworks |
| [grc/approval-workflows.md](grc/approval-workflows.md) | Ordered multi-step approval requests and the approver queue |
| [grc/remediation-tasks.md](grc/remediation-tasks.md) | Work records that link findings, risks and controls to an owner and date |

## Security

| Document | Purpose |
|---|---|
| [security/container-hardening.md](security/container-hardening.md) | Read-only filesystem, capabilities, WAF, and what is not hardened yet |
| [security/tenant-and-access-roadmap.md](security/tenant-and-access-roadmap.md) | Tenancy, RBAC/ABAC, audit trail, and security analytics sequencing |
| [security/forensics.md](security/forensics.md) | Authorized forensic lifecycle, custody, integrity, retention, and gaps |
| [security/forensic-compliance-mapping.md](security/forensic-compliance-mapping.md) | Forensic controls mapped to the framework catalogue |
| [security/data-loss-prevention.md](security/data-loss-prevention.md) | DLP boundaries, recognisers, review and quality measurement |
| [security/dlp-quality.md](security/dlp-quality.md) | False-positive rates, targets and review loop by identifier type |
| [security/network-access-rules.md](security/network-access-rules.md) | Platform-wide IP and email-domain allow/deny rules, enforcement and lockout recovery |
| [security/architecture-view.md](security/architecture-view.md) | One-page current/future security architecture, NIST CSF 2.0 and zero-trust overlay |
| [security/reference-register.md](security/reference-register.md) | External security and risk sources, their status, use and caveats |
| [security/audit-ledger.md](security/audit-ledger.md) | Hash-chained mirror of activity events: what it proves, pruning, verification |
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
