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

## Integrations

| Document | Purpose |
|---|---|
| [integrations/opencyberrange-grc-training.md](integrations/opencyberrange-grc-training.md) | Hands-on GRC training design and phased roadmap |

## Reference

| Document | Purpose |
|---|---|
| [API_DOCUMENTATION.md](API_DOCUMENTATION.md) | REST API |
| [mcp-server.md](mcp-server.md) | MCP server and AI tooling |
| [NOTIFICATIONS_GUIDE.md](NOTIFICATIONS_GUIDE.md) | Notification behaviour |
| [MediaProxy.md](MediaProxy.md) | Media handling |

## Health check

`php artisan opengrc:doctor` is the executable form of the operations documents.
Add `--json` for monitoring, `--strict` for CI, and `--skip-queue` when the queue
is inspected separately.
