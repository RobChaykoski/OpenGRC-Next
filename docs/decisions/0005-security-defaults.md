# 0005 — Security defaults for self-hosted deployments

Status: accepted
Date: 2026-11-17

## Context

The platform holds evidence, audit findings, vendor assessments, and the access
decisions that gate a trust center. It is a high-value target, and most
deployments will be run by a small team without a dedicated security engineer.
Defaults therefore do the work that a policy document cannot.

An audit of the codebase found: 2FA available but not required for privileged
roles; dynamic OAuth client registration enabled with no allowlist; sessions
unencrypted; HSTS commented out; uploads limited by size but not by content; an
authenticated-only private storage route with no per-record authorization; and no
tests proving that panels are isolated. Building the image also showed that the
documentation promised a WAF the image did not install.

## Decision

1. **MFA is required for `Super Admin` and `Security Admin`.** It remains optional
   for everyone else. Enrolment is reachable from the profile page, and
   `opengrc:doctor` reports privileged accounts without it.
2. **Dynamic client registration is off by default.** When enabled it requires an
   authenticated session and a configured redirect-domain allowlist.
3. **Sessions are encrypted by default**, and secure cookies are forced when the
   production app URL is HTTPS.
4. **HSTS is sent in production over HTTPS**, with `includeSubDomains` opt-in and
   no preload.
5. **Uploads are validated by content, not only by extension or client MIME.**
   Evidence has one rule set; images and imports have their own. Active content
   (SVG, HTML, JS, PHP) is rejected in every group.
6. **Private files are authorized per record.** The legacy
   `/app/priv-storage/{path}` route now checks the owning attachment or policy;
   evidence can be downloaded through a short-lived signed URL.
7. **Authorization is tested as a matrix.** Every admin resource is asserted
   reachable by a super admin and denied to a user with no permissions. This
   caught a real gap: vendor portal accounts had no policy at all.
8. **The container runs read-only with dropped capabilities**, and ships
   ModSecurity with the OWASP CRS in `DetectionOnly` mode. Enabling blocking is a
   documented, deliberate step.

## Consequences

- Privileged users must enrol before they can use the admin panel. That is
  friction on day one and a significant reduction in account-takeover impact.
- MCP clients cannot self-register by default. Operators create clients or opt
  in explicitly. This is less convenient and much safer.
- Existing sessions are invalidated by session encryption on upgrade. Users log
  in once.
- Some previously accepted attachments will now be rejected, notably SVG and
  HTML files. Evidence should not be active content.
- A WAF in detection mode produces log volume without blocking. That is the
  intended trade-off until false positives are reviewed.
- The authorization matrix test is slow (it walks every resource) and worth it.

## Alternatives considered

- **Force 2FA for every user.** Rejected for now: it blocks low-risk read-only
  accounts and increases support load before the training material exists.
- **Leave registration open but add rate limiting.** Rejected: rate limiting
  slows client minting, it does not authorize it.
- **Content-disarm uploads (strip active content) instead of rejecting.** Useful
  later for images; rejection is simpler and adequate for evidence.
- **Enable ModSecurity blocking on day one.** Rejected: Filament/Livewire
  false positives would lock users out of the product on first boot.
