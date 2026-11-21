# Network and registration access rules

Platform-wide IP and email-domain allow/deny lists for panel/API access and
account or request creation. See ADR 0047 for the decision.

Open **Admin → System → Access rules**.

## What a rule controls

| Field | Values | Meaning |
|---|---|---|
| Type | IP address or CIDR | Network origin |
| Type | Email domain | Signed-in user or requester domain |
| Effect | Allow | Starts or extends an allowlist for that dimension |
| Effect | Deny | Blocks a match; wins over any allow |
| Scope | Access | App, admin and vendor panels, plus the API |
| Scope | Registration | New staff/vendor accounts, API user creation, trust-center requests |
| Active | yes/no | Inactive rules are ignored |
| Expires | date/time | Expired rules are ignored and kept for history |

Semantics:

1. Any matching active deny rule blocks.
2. If active IP allow rules exist, a known IP must match one.
3. If active email-domain allow rules exist, a known email must match one.
4. The IP and email dimensions are independent.
5. No rules means no restriction.

Email domains are exact by default. Use `*.example.com` only when every
subdomain is trusted; `example.com` does not match `sub.example.com`.

## What it applies to

| Surface | IP rule | Email-domain rule |
|---|---|---|
| App panel (`/app`) | Before sign-in | After sign-in |
| Admin panel (`/admin`) | Before sign-in | After sign-in |
| Vendor portal (`/portal`) | Before sign-in | Vendor user's email |
| API (`/api`) | Every request | Authenticated token user |
| Staff account creation | Admin's request IP | New user's email |
| Vendor account creation | Admin's request IP | Vendor user's email |
| API user creation | Request IP | New user's email |
| Trust center request | Requester's IP | Requester's email |

The public trust center page itself is not blocked by `access` rules; only its
access request form is covered by `registration` rules.

## Operating it

1. Add a deny rule for a known hostile IP or domain and test it.
2. For an allowlist deployment, add every office/VPN IP or corporate domain
   **before** enabling a restrictive rule. An allowlist with no matching rule
   blocks everyone in that dimension.
3. Use `opengrc:access:rules` to inspect and test without the UI:
   ```bash
   php artisan opengrc:access:rules --scope=access
   php artisan opengrc:access:rules --scope=access --ip=203.0.113.10
   php artisan opengrc:access:rules --scope=registration --email=user@example.com
   php artisan opengrc:access:rules --disable=7
   ```
   A denied test exits non-zero, which makes it usable in a pre-change check.
4. Rotate or disable expired rules; expired rules stay visible for history.

## Lockout protection

The form refuses to save a rule that would deny the current administrator. That
includes enabling a matching deny, editing an allow rule so the admin no longer
matches, disabling the last matching allow rule, and deleting a matching rule.

If a lockout happens another way:

1. Set `ACCESS_RULES_ENABLED=false` in the environment and clear the config cache.
2. Disable the offending rule with `opengrc:access:rules --disable=ID`.
3. Re-enable the env flag and clear the cache again.

The kill switch is an exception, not a mode. Record why it was used.

## Logging and privacy

Allowed requests are not logged. Denied requests are logged as `access_rules`
events with the scope, IP, email **domain** (not the full address), user id, path,
method, reason and rule id. The response is a generic `403`; it does not reveal
the rule set.

## Limits

- IP matching uses Laravel's trusted-proxy configuration. Behind a proxy without
  trusted proxies, every request appears to come from the proxy.
- IPv4 and IPv6 are supported. IPv4-mapped IPv6 addresses are not normalised to
  IPv4.
- There is no per-application or per-OAuth-client list in this slice; the rules
  are platform-wide.
- There is no geolocation or ASN rule; use the WAF or upstream proxy for that.
- Email-domain rules act after authentication for panels. A denied user can
  authenticate but cannot use the panel.
