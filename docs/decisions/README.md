# Architecture and policy decisions

Short records of decisions that are expensive to reverse. One file per decision,
numbered, never edited after acceptance — supersede it instead.

Format:

```
# NNNN — Title
Status: proposed | accepted | superseded by NNNN
Date: YYYY-MM-DD

## Context
What forced a decision.

## Decision
What we chose.

## Consequences
What gets easier, what gets harder, and what we accept as debt.
```

| ID | Title | Status |
|---|---|---|
| [0001](0001-version-policy.md) | Supported-version policy and upgrade tiers | accepted |
| [0002](0002-training-integration-boundary.md) | Boundary between OpenGRC and OpenCyberRange | accepted |
| [0003](0003-framework-provenance.md) | Framework data ships as public-domain sets with a provenance register | accepted |
| [0004](0004-agpl-relicensing.md) | Relicense the community edition to AGPL-3.0-only | accepted |
| [0005](0005-security-defaults.md) | Security defaults for self-hosted deployments | accepted |
| [0006](0006-remove-mcp.md) | Remove MCP from the community edition | accepted |
| [0007](0007-three-role-authorization.md) | Replace granular RBAC with three roles | accepted |
| [0008](0008-evidence-ledger-and-migration-integrity.md) | Evidence ledger and migration change control | accepted |
| [0009](0009-continuous-control-monitoring.md) | Continuous control monitoring and vendor-neutral evidence connectors | accepted |
| [0010](0010-framework-scoping-engine.md) | Deterministic framework scoping before AI narrative | proposed |
| [0011](0011-discovery-engine.md) | Discovery engine as a separate service, not an OpenDLP port | accepted |
| [0012](0012-canonical-control-catalogue.md) | Canonical control catalogue is configuration, synced into the data graph | accepted |
| [0013](0013-curated-control-outcome-links.md) | Control-to-canonical links are curated and many-to-many | accepted |
| [0014](0014-policy-and-evidence-coverage.md) | Policies link directly to outcomes; evidence inherits through its control | accepted |
| [0015](0015-assurance-score-and-confidence.md) | Assurance score is component-visible and confidence is derived | accepted |
| [0016](0016-evidence-pack-boundary.md) | Evidence packs index evidence; they do not export payloads | accepted |
| [0017](0017-connector-authentication-requests.md) | Authentication requests for evidence connectors | accepted |
| [0018](0018-single-console-discovery-integration.md) | OpenGRC is the single console; the discovery engine stays a headless GPL service | accepted |
| [0019](0019-forensic-evidence-handling.md) | Forensic collection is authorized, scoped and append-only; raw content stays in a collector | accepted |
| [0020](0020-openmetadata-metadata-source.md) | OpenMetadata is a read-only catalog source; OpenGRC keeps the compliance console | accepted |
| [0021](0021-detection-rules-as-evidence.md) | YARA and Sigma are detection evidence; the console does not execute rules | accepted |
| [0022](0022-adversary-emulation-evidence.md) | Adversary emulation runs in the range; OpenGRC consumes the summary | accepted |
| [0023](0023-wstg-security-testing-evidence.md) | WSTG is a test methodology; reference IDs, do not copy the guide | accepted |
| [0024](0024-exception-scope-and-expiry.md) | Exceptions are scoped, approved and expire by default | accepted |
| [0025](0025-assurance-report-register.md) | Assurance reports are a register, not a control catalogue | accepted |
| [0026](0026-privacy-request-register.md) | Privacy requests are regime-aware register entries, not audit requests | accepted |
| [0027](0027-non-human-identity-register.md) | Non-human identities are a register, not a secret store | accepted |
| [0028](0028-sbom-and-osv-supply-chain-evidence.md) | SBOM and OSV are supply-chain evidence, not a scanner | accepted |
| [0029](0029-openbao-secret-metadata.md) | OpenBao owns the secret; OpenGRC owns the identity register | accepted |
| [0030](0030-guidance-register.md) | Guidance is a register, not a framework | accepted |
| [0031](0031-risk-acceptance.md) | Risk acceptance is a dated decision, not a mitigation note | accepted |
| [0032](0032-policy-acknowledgement.md) | Policy acknowledgement is a versioned attestation, not a click | accepted |
| [0033](0033-access-review-campaign.md) | Access review is a campaign snapshot, not a live role list | accepted |
| [0034](0034-exercise-register.md) | Exercises are a register, not a training transcript | accepted |
| [0035](0035-sarif-assessment-import.md) | Scanner output is findings evidence, not a scanner | accepted |
| [0036](0036-deps-dev-scorecard-enrichment.md) | deps.dev and Scorecard enrich components; they do not decide compliance | accepted |
| [0037](0037-observable-enrichment-cortex.md) | Observable enrichment is opt-in and privacy-gated | accepted |
| [0038](0038-cset-assessment-import.md) | CSET is an external assessment; import the scrubbed export only | accepted |
| [0039](0039-regulatory-change-register.md) | Regulatory change is a human register, not a feed | accepted |
| [0040](0040-dlp-quality-targets.md) | DLP false-positive targets are programme targets, not a universal benchmark | accepted |
| [0041](0041-control-attestation.md) | Control owner attestation is a dated statement, not a control test | accepted |
| [0042](0042-control-crosswalk.md) | A control crosswalk is a reviewed mapping, not equivalence | accepted |
