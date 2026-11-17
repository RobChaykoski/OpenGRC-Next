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
| [0007](0007-three-role-authorization.md) | Replace granular RBAC with three roles | accepted |
