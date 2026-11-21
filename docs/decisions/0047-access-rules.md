# 0047 — Access rules are platform-wide IP and email-domain allow/deny lists

Status: accepted
Date: 2026-11-20

## Context

OpenGRC has four access surfaces (app, admin, vendor portal, API) and three
registration-like paths (admin-created staff accounts, vendor accounts, and
public trust-center access requests). Self-service signup is closed, so
"registration" is not a single route that can be wrapped in one middleware.

Operators need to:

- block a hostile network or a compromised account domain;
- allow only corporate networks or email domains in a regulated deployment;
- apply the same decision to sign-in, API access, and account/request creation;
- change a rule without a deployment, and recover from a bad rule.

The obvious shortcut — a setting in `.env` or a WAF rule — is not a product
control: it cannot be changed by the administrator, has no audit trail, and does
not understand email domains or registration paths.

## Decision

1. **One platform-wide rule table**, `access_rules`, with:
   - `type`: `ip` (single address or CIDR) or `email_domain`;
   - `effect`: `allow` or `deny`;
   - `scope`: `access` or `registration`;
   - `value`, `note`, `is_active`, `expires_at`, `created_by`.
2. **Evaluation semantics:**
   - any matching active deny rule denies;
   - if active IP allow rules exist for the scope, a known IP must match one;
   - if active email-domain allow rules exist, a known email must match one;
   - the IP and email dimensions are independent;
   - inactive and expired rules are ignored.
3. **Email domains are exact by default.** `acme.com` matches only the apex;
   `*.acme.com` is an explicit opt-in for subdomains. Wildcards are not inferred.
4. **Enforcement points:**
   - `access`: app, admin and vendor panel middleware (IP before auth, email
     domain after auth), and the API group plus authenticated API routes;
   - `registration`: admin-created staff users, vendor users, the API user-create
     endpoint, and trust-center access requests.
5. **Deny wins and the response is generic.** The UI never lists the rule that
   matched. The event log records the dimension, rule id, path, IP and email
   domain (never the full email).
6. **Only administrators may read or change rules.** `AccessRulePolicy` covers
   the resource. The resource lives in the admin panel; hiding is not
   authorization.
7. **Self-lockout is refused.** Creating, editing, enabling or deleting a rule
   that would deny the current administrator is blocked with a field error. The
   environment kill switch `ACCESS_RULES_ENABLED=false` and
   `opengrc:access:rules --disable=ID` are recovery paths.
8. **CLI account creation applies email rules but not IP rules**, because a
   console call has no meaningful request IP. This is a deliberate recovery
   path, documented and tested; it does not bypass an email-domain deny.
9. **Rules are cached for five minutes and invalidated on save/delete.** A
   missing table (install, unmigrated database) allows rather than locks out.

## Consequences

- An allowlist deployment must seed at least one IP or email domain before it
  can rely on the rule; an empty list still allows everyone.
- Blocking by email domain only takes effect after authentication. The login page
  remains reachable by IP; the authenticated request is denied and the session
  cannot use the panel.
- A WAF or reverse proxy is still the right place for volumetric and
  network-layer filtering. These rules are an application control, not a
  replacement for infrastructure protection.
- The kill switch is a security exception, not a feature. It is environment-only
  and should be reverted as soon as the bad rule is disabled.
- IP rules use Laravel's trusted-proxy configuration. A deployment that does not
  configure trusted proxies will see the proxy address, not the client.
- The public trust center is not covered by `access` rules; it keeps its own
  magic-link/NDA model. Its request form is covered by `registration` rules.
