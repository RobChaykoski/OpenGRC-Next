# Data loss prevention

Two boundaries matter more than any scanner feature:

1. **AI egress.** HR, special-category, and credential content must never leave
   the application through an AI provider. `AiService::chatCompletion` runs the
   DLP guard before it builds a request. There is no setting that disables this
   for HR or special-category data.
2. **Upload storage.** Credentials are blocked. HR and special-category content
   is flagged and allowed because a background-check or audit artifact may
   legitimately contain it; the record must then be stored privately and
   access-controlled, not silently rejected.

## What runs

`App\Services\Dlp\DlpService` scans text with:

- **Native recognisers** (`RegexDlpScanner`): email, phone, payment cards
  (Luhn-checked), Canadian SIN, US SSN, AWS keys, private keys, JWTs, secret
  assignments, criminal/background-check terms, medical and biometric terms,
  protected characteristics, and HR/personnel terms.
- **Presidio** (optional): point `PRESIDIO_URL` at a **local** sidecar. Presidio
  is MIT-licensed. Do not point it at a third party; the text being scanned may
  itself be sensitive. If Presidio is unavailable the native scanner still runs.

Matched values are **never stored or logged**. A finding stores the source, the
type labels, severity, action (`blocked` / `flagged`), a SHA-256 of the scanned
content, the actor, and an optional morph context.

## Findings

`dlp_findings` is the review queue. It does not contain the content. A finding
for an upload can be correlated to the stored record by the context morph and
the content hash. Review findings before allowing an exception.

## AI behaviour

- HR, special-category, and credential content is blocked before any HTTP call.
  The UI shows a clear "AI request blocked" message and the attempt is recorded.
- Clean prompts are still scanned and flagged if they contain personal data; the
  prompt is sent as before.
- A new AI integration must go through `AiService` or run the guard explicitly.
  Calling a provider directly bypasses the control; treat that as a defect.

## Upload behaviour

- Text, CSV, JSON, Markdown, DOCX, and XLSX are inspected. PDFs, images, and
  archives are not inspected yet; they need OCR/PDF extraction or an external
  scanner.
- Credentials are blocked.
- HR and special-category content is flagged and allowed. Put that evidence on
  the private disk and restrict access; background-check evidence is not public
  and must not be sent to AI.

## Open-source DLP engines

| Engine | Licence | Use |
|---|---|---|
| **Microsoft Presidio** | MIT | PII detection and anonymisation; the recommended optional sidecar |
| **Gitleaks** | MIT | Secrets and credential scanning in files and repositories |
| **LLM Guard** | MIT | Prompt/response scanning, PII, secrets, prompt injection |
| **ClamAV** | GPL-2.0 | Malware scanning; complementary, not DLP |
| **OpenDLP** | GPL-3.0 | Historical code, but the discovery model is under evaluation as a separate service (`opendiscoveryengine`). Do not link the Perl/C code into OpenGRC; integrate over an API |

There is no turnkey open-source DLP product here. Content extraction (PDF, OCR,
images), entity tuning, and review workflow are the hard parts. Start with the
native recognisers, add Presidio as a local sidecar, and keep the review queue
small enough that someone actually reads it.

## Configuration

```dotenv
DLP_UPLOAD_SCAN=true
PRESIDIO_URL=http://presidio-analyzer:5001
PRESIDIO_TIMEOUT=5
DLP_MAX_SCAN_BYTES=2097152
```

`config/dlp.php` holds the blocked types for AI and uploads.

## Verification

```bash
php artisan test --filter=Dlp
```

The suite covers recognisers, AI blocking before the HTTP call, upload blocking,
HR/special-category flagging, and the no-content-in-findings invariant.
