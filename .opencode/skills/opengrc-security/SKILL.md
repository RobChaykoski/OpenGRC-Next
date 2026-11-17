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
php artisan opengrc:doctor --strict
```

Every control you touch should leave a test behind. The existing suite already
covers security headers, upload content checks, private path traversal, signed
downloads, panel isolation, and the role matrix; extend it rather than testing by
hand.

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
