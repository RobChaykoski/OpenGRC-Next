---
name: OpenGRC Security
description: Use when changing or reviewing authentication, authorization, sessions, security headers, uploads, private file access, API tokens, secrets, logging, or container hardening in OpenGRC. Covers the threat model, the controls that already exist, the evidence each one needs, and the defaults that may not be weakened without an ADR.
---

# OpenGRC Security

OpenGRC holds audit evidence, control implementations, risk decisions, vendor
assessments, and the access rules that gate a public trust center. It is
self-hosted, usually by a small team without a security engineer. Defaults have to
carry the weight that a policy document cannot.

Read `docs/decisions/0005-security-defaults.md` before changing a default, and
`docs/security/container-hardening.md` before changing deployment shape.

## Threat model in one table

| Asset | Who attacks it | Realistic path |
|---|---|---|
| Evidence files and audit findings | Curious authenticated user, stolen session | Guess a storage path, replay a download URL |
| Admin panel | Credential thief, phished editor | Password reuse, no second factor, long-lived session |
| Trust center documents | Competitor, unapproved prospect | Public link sharing, expired magic link still valid |
| Vendor portal | Vendor account takeover | Shared password, missing password-change gate |
| The host | Remote attacker via an upload or dependency | Active content upload, malicious package |
| Secrets | Anyone with log or settings access | Keys in `.env`, decrypted settings, debug pages |

## Apply this continuously

Security is not a review at the end of a feature. Run this loop on every change:

1. **Before building — threat model in five lines.** Asset, actor, realistic
   path, control, test. If you cannot name the test, the control is not designed.
   Public routes, uploads, AI prompts, exports, tokens and access rules need this
   every time.
2. **While building — the default is deny.** Check authorization at every query,
   validate at the server, escape at output, keep secrets out of logs, and test
   the negative path (403/404/410/422), not only the happy path.
3. **Design review — include the unsafe states.** Permission denied, expired,
   revoked, blocked, empty, error, long content, dark/light, keyboard. A screen
   that only looks right for a successful admin is not designed.
4. **Before commit — security tests and the gates.** Add or extend a test for
   every control touched. Run the targeted security filters, then the full suite,
   Pint, PHPStan, `opengrc:doctor`, and the migration/evidence checks.
5. **After an incident or a surprise — write it down.** Turn the lesson into a
   test, a default, a skill line, or an ADR. A lesson that lives only in a chat
   is repeated next quarter.

Useful filters:

```bash
php artisan test --filter=Security
php artisan test --filter=Authorization
php artisan test --filter=AccessRule
php artisan test --filter=Assistant
php artisan test --filter=AuditPortal
```

## Design security testing

Design is part of the attack surface. Check it in the component or browser test:

- The page renders for the allowed role and returns 403 for the others.
- Empty, loading, error, expired/revoked, and permission-denied states exist.
- No secret, full token, full card number, full government ID, password, API key,
  or raw matched value appears in HTML, a title, a tooltip, an error, or a URL
  the page renders.
- User-supplied HTML is escaped or sanitized; the model's answer is rendered as
  text, not HTML.
- A destructive action names the object, shows the consequence, and requires
  confirmation; a safe default exists.
- Forms have labels, help text, and field-level errors; success and failure are
  announced, not only coloured.
- Tables have headers and a caption or accessible name; status is not colour-only.
- Both themes and a narrow viewport are checked for new screens.
- A link or capability token is not sent in a referrer; responses set
  `Referrer-Policy: no-referrer`, `X-Robots-Tag`, and `Cache-Control: no-store`
  where the URL is a secret.

Where Filament tests cannot express a visual check, state what was opened by hand
and at which role. Do not claim a browser check that did not happen.

## Authentication

- Three roles only: `admin`, `editor`, `viewer` (`app/Enums/UserRole.php`). Do not
  add permission strings or a fourth role without an ADR.
- MFA is **required for admins**, optional for others
  (`RequireTwoFactorAuthentication`). Admins who have not enrolled are redirected
  to their profile, and `opengrc:doctor` reports them.
- The vendor portal is a separate guard and password broker (`vendor`). Vendor
  accounts are not internal users and must never be given an internal session.
- Sessions are encrypted. Keep `SESSION_ENCRYPT` and secure cookies on; the
  timeout comes from `security.session_timeout` and the inactivity guard is a
  second line of defence, not the control itself.
- Password changes go through Breezy's rules (minimum 12, mixed case,
  uncompromised). Do not relax them for convenience.

## Authorization

- `AppPolicy` covers ordinary models. Six models keep explicit policies for
  ownership or isolation: audit items, data requests, data request responses,
  file attachments, users, vendor users.
- `Gate::before` maps permission-style abilities (`List`, `Read`, `View`,
  `Create`, `Update`, `Manage`) onto the role. Settings pages rely on this; do not
  reintroduce granular permissions to make one page work.
- Every query in a widget, export, report, or API controller is subject to the
  same rules as the resource. A count that ignores policy is an information leak.
- `$user->canAccessPanel()` returning true is not authorization. The policy still
  decides what the user may do once inside.

## Request and response hardening

`SecurityHeaders` sends `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy`, HSTS in production over HTTPS, and a CSP
for HTML responses. `TrustHosts` and `StripHostOverrideHeaders` close host-header
attacks.

Rules:

- Add a CSP source only when a feature genuinely needs it, and prefer self-hosted
  assets. The trust center's CDN scripts are a known exception, not a precedent.
- HSTS stays production-only. Do not send it from a development host.
- New outbound origins (storage, SSO, mail images) must be added to the policy
  deliberately, with a test in `tests/Unit/SecurityHeadersTest.php`.
- Do not disable CSRF for a Filament route to fix a form.

## Uploads and private files

Two classes own this, and both must stay in step:

| Concern | Source of truth |
|---|---|
| Extensions, MIME types, size limits | `App\Support\UploadRules` |
| Content inspection and rejection | `App\Services\UploadValidationService` |
| Allowed private directories | `App\Services\PrivateFileAccess::PREFIXES` |
| Per-path authorization | `App\Services\PrivateFileAccess::authorize` |
| Signed downloads | `attachments.download` temporary signed route |

Rules:

- Filament's browser `accept` list is a hint. Always attach
  `UploadRules::validationRule($group)` so the server inspects the bytes.
- Active content (SVG, HTML, JS, PHP, executables) is rejected in every group.
  Do not add an exception "just for this one template".
- Evidence belongs on the private disk. Public disk is for logos and other
  deliberately public assets only.
- A new storage prefix must be added to `PREFIXES`, given a modelled owner, and
  covered by an authorization test. A path without an owner falls back to a
  blanket permission check.
- Never serve a file by joining user input onto a storage path. Reject `..`,
  backslashes, NUL bytes, and absolute paths.

## Output encoding and rich text

- Rich text is sanitized on the way in (`SanitizedHtml` cast) and again for PDF
  (`HtmlSanitizerService::sanitizeForPdf`). Do not render raw model HTML with
  `{!! !!}` unless it passed through the sanitizer.
- Filament's `->html()` is safe; hand-written Blade that echoes `details`,
  `response`, or policy bodies is where XSS returns.
- File names, descriptions, and codes are user input too. Escape them in Blade
  and in PDF templates.

## Secrets, tokens, and logs

- Provider API keys are stored encrypted in settings or supplied by env. When
  reading one, decrypt; never print it, never return it through an API, and mask
  it in the settings form.
- API access is Sanctum-based today, with Passport available. A token carries the
  user's role; revoking the token must be enough to end access.
- `activity_log`, `AppLogger`, and exception output must not contain passwords,
  tokens, session IDs, full request bodies, or decrypted keys. Log identifiers,
  not payloads.
- Production runs with `APP_DEBUG=false`. If an error page shows a stack trace,
  treat it as a security defect, not a display bug.

## Deployment

The reference Compose file is the tested shape: read-only root filesystem,
`cap_drop: ALL`, `no-new-privileges`, PID and memory limits, separate worker, and
ModSecurity with the OWASP CRS in `DetectionOnly`.

- Blocking WAF mode is a deliberate change after reviewing false positives. Do
  not flip it in a feature PR.
- `php artisan opengrc:doctor --strict` is the deployment gate. It checks debug
  mode, keys, storage, migrations, privileged accounts without MFA, and the queue.
- Dependency additions must pass `docs/OSS-POLICY.md` and
  `php scripts/check-licenses.php`. No pre-release, abandoned, or unmaintained
  package, regardless of how small the need.

## Defaults that need an ADR to weaken

1. MFA required for admins
2. Closed OAuth client registration
3. Encrypted sessions and secure cookies
4. HSTS in production
5. Content-checked uploads
6. Per-record authorization for private files
7. Read-only container with dropped capabilities
8. `APP_DEBUG=false` in production

If a change weakens one of these, stop and write the ADR in `docs/decisions/`
with the consequence stated plainly. "Temporarily" is how permanent regressions
start.

## Verification

```bash
php artisan test --filter=Security
php artisan test --filter=PrivateFileAccess
php artisan test --filter=Upload
php artisan test --filter=Authorization
php artisan test --filter=AccessRule
php artisan test --filter=Assistant
php artisan test --filter=AuditPortal
php artisan opengrc:doctor --strict
```

Every control you touch should leave a test behind. The existing suite already
covers security headers, upload content checks, private path traversal, signed
downloads, panel isolation, and the role matrix; extend it rather than testing by
hand.

## Lessons learned

These are the failures that cost the most time, not abstract principles.

| Lesson | What to do |
|---|---|
| A capability URL is a secret in the path | Store only a hash; show once; set `Referrer-Policy: no-referrer`, `X-Robots-Tag` and `Cache-Control: no-store`; rate-limit; log reads; support revoke and expiry |
| AI prompts are egress | Run DLP first; send bounded facts, never payloads or document bodies; treat retrieved data as untrusted; render the answer as text; label it as interpretation |
| A blocked prompt must not be stored | Persist a redaction or hash, not the credential/HR content that triggered the block. Test that the secret is absent from the run row |
| Allowlists invert the default | An allow rule turns its dimension into a deny-by-default list. Test the unmatched case, the deny-wins case, and the empty-list case |
| Security settings can lock the operator out | Refuse a rule that denies the current session; provide an environment kill switch and a CLI disable path; test recovery |
| Generic error pages are safer than detailed ones | A 403 should not enumerate rules, tokens or records. Put the useful explanation in the authenticated UI, not the error body |
| Aggregate screens leak too | Every count, chart, export and AI query must respect record permissions and small-cell rules |
| Hidden actions are not authorization | The policy must deny even when the button is invisible. Test the direct URL and the Livewire action |
| The negative path is the product | Expired, revoked, blocked, forbidden, empty and partial states are the security behaviour; test them before the happy path |
| A design check is a security check | A missing confirmation, a visible secret, colour-only status or an inaccessible control is a defect. Record what was opened by hand and in which role |

Turn a new lesson into a test in the same change. A skill line without a test is
a preference.

## Known gaps (state them, do not paper over them)

- CSP still allows `unsafe-inline` and `unsafe-eval` for Livewire and Alpine.
  Moving to nonces and Alpine's CSP build is a project, not a config edit.
- The container process runs as root inside the image; the unprivileged-port
  migration is planned but not done.
- Base images are tag-pinned, not digest-pinned, and images are not signed.
- Secrets are environment variables in the reference deployment.
- ModSecurity ships in detection-only mode.
- `spatie/laravel-permission` remains installed only because historical
  migrations reference its models.
