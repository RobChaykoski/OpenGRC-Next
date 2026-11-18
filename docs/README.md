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
| [FUTURE-ROADMAP.md](FUTURE-ROADMAP.md) | Sequencing for the nine platform pillars |

## Integrations

| Document | Purpose |
|---|---|
| [integrations/opencyberrange-grc-training.md](integrations/opencyberrange-grc-training.md) | Hands-on GRC training design and phased roadmap |

## GRC practice

| Document | Purpose |
|---|---|
| [grc/README.md](grc/README.md) | Capability map, maturity levels, stated gaps |
| [grc/framework-catalog.md](grc/framework-catalog.md) | Shipped frameworks, licensing, provenance |
| [grc/framework-scoping.md](grc/framework-scoping.md) | Profile → framework/control/evidence scoping |
| [grc/risk-methodology.md](grc/risk-methodology.md) | 5×5 scoring, treatment, appetite, cadence |
| [grc/evidence-standard.md](grc/evidence-standard.md) | What counts as evidence and how to store it |
| [grc/audit-playbook.md](grc/audit-playbook.md) | Audit lifecycle, findings, closure |
| [grc/program-playbook.md](grc/program-playbook.md) | Roles, cadence, metrics, onboarding |
| [grc/data-dictionary.md](grc/data-dictionary.md) | Objects, fields, relations, invariants |

## Security

| Document | Purpose |
|---|---|
| [security/container-hardening.md](security/container-hardening.md) | Read-only filesystem, capabilities, WAF, and what is not hardened yet |
| [security/tenant-and-access-roadmap.md](security/tenant-and-access-roadmap.md) | Tenancy, RBAC/ABAC, audit trail, and security analytics sequencing |
| [OSS-POLICY.md](OSS-POLICY.md) | License, dependency allowlist, contribution model |
| [../SECURITY.md](../SECURITY.md) | Vulnerability disclosure |

## Reference

| Document | Purpose |
|---|---|
| [API_DOCUMENTATION.md](API_DOCUMENTATION.md) | REST API |
| [NOTIFICATIONS_GUIDE.md](NOTIFICATIONS_GUIDE.md) | Notification behaviour |
| [MediaProxy.md](MediaProxy.md) | Media handling |

## Health check

`php artisan opengrc:doctor` is the executable form of the operations documents.
Add `--json` for monitoring, `--strict` for CI, and `--skip-queue` when the queue
is inspected separately.

`php artisan opengrc:framework-import` imports a control library by catalog code;
`php artisan opengrc:framework-audit` validates imported data against the
provenance register in `config/framework_catalog.php`.
