# 0006 — Remove MCP from the community edition

Status: accepted
Date: 2026-11-17

## Context

The MCP server (Model Context Protocol) exposed the full data model to external AI
assistants over OAuth. It shipped inside this repository while the README
described it as an enterprise feature, and `docs/OSS-POLICY.md` flagged the
contradiction as unresolved.

Keeping it had three costs:

1. **Ambiguous boundary.** Community users could not tell what was open source
   and what was commercial, which undermines the AGPL decision in ADR 0004.
2. **Security surface.** An OAuth-protected endpoint that can read and write
   every entity is the largest attack surface in the application. It requires
   scoped tokens, tool-level authorization, prompt-injection defences, and its
   own rate limiting — a product in itself.
3. **Dependency weight.** `laravel/mcp` and its dev tooling pulled in an OAuth
   authorization-view override, dynamic client registration, and a classmap of
   tools that had to be maintained alongside every model change.

## Decision

Remove MCP from the community edition:

- Delete `app/Mcp`, the MCP routes, middleware, OAuth registration controller,
  settings UI, seeder, tests, views, `.mcp.json`, and `config/mcp.php`.
- Remove the `laravel/mcp` dependency, and `laravel/boost` (dev tooling) which
  required it.
- Add a migration that deletes leftover `mcp.*` settings.
- Keep Passport and the REST API. The API is a documented, scoped surface and is
  not coupled to MCP.

MCP remains an enterprise/hosted capability. If it returns to this repository,
it returns under the AGPL with the security controls described in ADR 0005.

## Consequences

- The community edition has a clear boundary: no AI-assistant endpoint, no OAuth
  client registration, no tool classmap to maintain.
- AI features that call a provider directly (risk assist, gap analysis) remain.
  They do not expose the data model to third parties and run with the operator's
  own key.
- Three test files and roughly a dozen MCP-specific files are removed; the suite
  is smaller and faster.
- Integrations that depended on the MCP endpoint must use the REST API or the
  enterprise edition. This is a breaking change for them and must be in the
  release notes.
- ADR 0005's dynamic-client-registration decision is moot in this repository;
  keep it as a record of the reasoning if MCP returns.

## Alternatives considered

- **Keep MCP but feature-flag it off by default.** Rejected: the code and
  dependency remain, the boundary stays ambiguous, and "off by default" is not a
  security control.
- **Move MCP to a separate package in this repo.** Rejected: still community
  code, still AGPL, so the boundary problem remains.
- **Keep it and change the README to call it community.** Rejected: it would make
  the largest security surface part of the open-source promise without the
  maintenance capacity to support it.
