# Web security testing evidence (OWASP WSTG)

OpenGRC does not run web security tests. Testers use ZAP, Burp, Nessus, a proxy
and their own judgement; OpenGRC stores the assessment summary and the evidence
reference. The methodology is the OWASP Web Security Testing Guide (WSTG), and
the requirements side is usually OWASP ASVS.

See ADR 0023 for the boundary. The short version: reference the guide, do not
copy it; record coverage, not request bodies.

## Why WSTG and not a scanner report

- **ASVS** says which security requirements an application should meet.
- **WSTG** describes how to test whether it does.
- **ZAP/Nessus/Burp** automate part of the testing.

A clean scanner report is not a completed WSTG assessment. Business logic,
authorization design and process controls need a person. The evidence should say
what was planned, what was executed, and what failed.

## Request

```http
POST /api/v1/evidence
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json

{
  "id": "security-testing:acme:app-42:2026-11-20",
  "sha256": "<sha256 of the evidence object>",
  "evidenceType": "security-testing",
  "capturedAt": "2026-11-20T10:00:00Z",
  "controlIdentifier": "SA-11",
  "evidence": {
    "schema": "opengrc.security-testing/v1",
    "methodology": "owasp-wstg",
    "methodologyVersion": "v42",
    "applicationId": "app-42",
    "applicationName": "Customer portal",
    "environment": "staging",
    "scope": ["https://portal.example"],
    "testedAt": "2026-11-20T09:00:00Z",
    "tester": "appsec-team",
    "testsPlanned": 90,
    "testsExecuted": 88,
    "testsPassed": 80,
    "testsFailed": 8,
    "testsNotApplicable": 2,
    "coveragePercent": 97.8,
    "categories": {
      "INFO": {"planned": 10, "executed": 10, "failed": 0},
      "ATHN": {"planned": 10, "executed": 10, "failed": 1},
      "ATHZ": {"planned": 8, "executed": 8, "failed": 2}
    },
    "failedTestIds": ["WSTG-v42-ATHN-01", "WSTG-v42-ATHZ-02"],
    "evidenceReferences": ["zap-scan-123", "report-sha256:..."],
    "notes": null
  }
}
```

`sha256` is the canonical JSON of `evidence`: recursively key-sorted, compact
separators, UTF-8, unescaped slashes. It is the same hash rule as the other
external evidence types.

| Field | Required | Meaning |
|---|---|---|
| `methodology` | yes | `owasp-wstg` |
| `methodologyVersion` | yes | Guide version, for example `v42` or `5.0` |
| `applicationId` | yes | The OpenGRC application reference |
| `applicationName` | no | Human label; not an identifier |
| `environment` | yes | Where the tests ran: staging, pre-production, production |
| `scope` | no | Hosts or URLs in scope; no credentials or query strings with secrets |
| `testedAt` | yes | When the test session ran, in UTC |
| `tester` | no | Team or role, not a personal profile |
| `testsPlanned` | yes | Tests selected from the guide |
| `testsExecuted` | yes | Tests actually run |
| `testsPassed` | yes | Tests with a passing result |
| `testsFailed` | yes | Tests with a failing result |
| `testsNotApplicable` | yes | Tests excluded, with a reason in the report |
| `coveragePercent` | no | Producer-computed executed/planned |
| `categories` | no | Counts by WSTG category code |
| `failedTestIds` | no | Version-qualified IDs only |
| `evidenceReferences` | no | Report ids or hashes; not the report payload |
| `notes` | no | Short operational note, never a request or response |

## Versioned identifiers

WSTG identifiers may change between versions. Prefer
`WSTG-v42-ATHN-01`; if the producer sends `WSTG-ATHN-01`, the payload's
`methodologyVersion` is the qualifier. Do not compare results from different
versions as if they were the same test.

The guide itself is the source of truth:
<https://owasp.org/www-project-web-security-testing-guide/>. Link to the
versioned page, not to a moving "latest" URL.

## Status and validation

The record is stored as `unknown`. A validation rule decides the control. For an
application security verification objective:

```json
{
  "match": { "evidence_type": "security-testing" },
  "assert": [
    { "path": "methodology", "op": "eq", "value": "owasp-wstg" },
    { "path": "coveragePercent", "op": "gte", "value": 90 },
    { "path": "testsFailed", "op": "eq", "value": 0 }
  ]
}
```

A failed test is a finding, not an automatic control failure: record the finding,
the remediation and the exception. A missing value is Unknown, never a pass.

Typical control links:

| Control family | What the evidence supports |
|---|---|
| NIST 800-53 SA-11, RA-5, SI-10 | Developer testing, vulnerability scanning, input validation |
| ISO 27001 A.8.25, A.8.26, A.8.28 | Secure development, application security requirements, secure coding |
| SOC 2 CC4.1, CC7.1 | Control monitoring and evaluation |
| PCI DSS 6.3, 11.3 | Secure software and penetration testing |

## Scope, evidence and privacy

- Test only systems you are authorized to test, in an approved environment.
- Do not send cookies, bearer tokens, credentials, personal data, request
  bodies, response bodies or screenshots containing secrets.
- Keep the full report in the testing tool or a private evidence store; send the
  reference and hash to OpenGRC.
- A finding may need a risk record, an exception or a remediation task; link it
  rather than hiding it in a pass/fail count.
- A production test needs a change window, a stop condition and a rollback plan.

## Re-test triggers

Re-test after a material change: a new authentication flow, a new authorization
model, a public endpoint, a dependency with a security release, a major release,
or an incident. An annual test alone leaves most of the year unmeasured.

## Licensing

The WSTG is CC BY-SA 4.0. OpenGRC references its identifiers and links to the
official guide; it does not copy the prose or the checklist spreadsheets. If a
future feature mirrors any WSTG text, it needs attribution, a licence note and a
share-alike review before it merges.

## Limits

- No scanner, crawler, request replayer or exploit runner in OpenGRC.
- No request/response content, tokens or personal data in the evidence.
- No automatic control verdict; a validation rule decides.
- No claim that an assessment covers the whole application or a later release.
- No copy of the WSTG document; link to the versioned guide.
