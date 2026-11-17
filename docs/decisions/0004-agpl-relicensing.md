# 0004 — Relicense the community edition to AGPL-3.0-only

Status: accepted
Date: 2026-11-17

## Context

The repository was distributed under the Elastic License 2.0, with earlier
releases under MIT and CC BY-NC-SA 4.0. Elastic 2.0 is source-available, not open
source: it forbids offering the software as a hosted service and is not
OSI-approved. `composer.json` still declared MIT, so the project stated three
different licenses in three places.

The product is self-hosted infrastructure for governance and compliance work. Its
users are, by definition, sensitive to supply-chain and licensing risk. A
"source-available" license undermines the trust the product is trying to sell, and
a GRC vendor that cannot state its own license consistently is not credible.

At the same time, the project sells a hosted/MSSP edition and needs protection
against a competitor taking the community edition and reselling it as a service.

## Decision

Adopt **AGPL-3.0-only** for the community edition in this repository.

1. `LICENSE.txt` is the AGPL text; `composer.json` declares `AGPL-3.0-only`;
   `NOTICE` records copyright, license history, and trademark ownership.
2. Contributions are inbound = outbound under the AGPL, certified with a
   Developer Certificate of Origin. No CLA.
3. Commercial features live outside this repository. Anything merged here is
   AGPL.
4. Dependency licenses are gated in CI against the allowlist in
   `docs/OSS-POLICY.md`.
5. Prior releases remain available under the terms they were published with.

## Consequences

- The community edition is genuinely open source, and the license cannot be
  unilaterally changed back without every contributor's consent.
- A competitor hosting a modified version must publish their source. That is the
  AGPL's purpose and the reason it was chosen over Apache-2.0.
- The enterprise edition cannot import community code while staying proprietary
  unless the project returns to a CLA. That trade-off is accepted: the enterprise
  edition is built separately.
- Deployments now have an obligation to offer source to network users. This is
  documented in `docs/OSS-POLICY.md` and should be surfaced in the UI footer as a
  "Source code" link in the release that follows.
- Code contributed before this decision under the Elastic license remains
  licensed as it was. The maintainer should obtain written confirmation from the
  external contributors listed below before asserting a clean relicensing of
  their specific contributions:
  - Austin Montoya
  - mdrissel
  - Steven Hunt
  - Abdiiir
  - Shukla-Aaryan

  This is an administrative action, not a code change. It is recorded here so it
  is not lost.

## Alternatives considered

- **Keep Elastic 2.0.** Rejected: it is not open source, which conflicts with the
  project's stated direction, and it required explaining "source-available" in
  every procurement conversation.
- **Apache-2.0.** Rejected: maximum adoption, but no protection against a hosted
  competitor and no reciprocal contribution.
- **AGPL plus a CLA for dual licensing.** Rejected: a CLA is the standard way to
  keep dual-licensing options open, but it is contributor-hostile and contradicts
  the "as open source as possible" goal. If dual licensing becomes essential,
  this decision must be superseded, not quietly amended.
