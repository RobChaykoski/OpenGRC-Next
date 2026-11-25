# Publication-sensitive data

Status: **implemented policy and detector controls**. This page covers the DLP
workstream for material that must not appear in a public repository or an
outbound document.

## Data classes

| Code | Level | What it covers |
|---|---|---|
| `infrastructure_metadata` | Confidential | Private and public IPv4 addresses, internal hostnames, topology, cloud metadata endpoints |
| `assessment_answer_key` | Restricted | Lab flags and expected answer values |
| `secret_material` | Restricted | Credentials, API tokens, private keys, connection strings |

## Policies

| Code | Action | Channels | Scope |
|---|---|---|---|
| `PUB-001` | Block | Source repository, documentation, upload | Answer keys, secrets, and unreviewed infrastructure metadata |
| `PUB-002` | Block | Upload, cloud, web, email | Assessment answer keys leaving the assessment boundary |
| `PUB-003` | Alert | Repository, documentation, email, web | Infrastructure metadata that needs review and redaction |
| `PUB-004` | Quarantine | All publication and outbound channels | Secret material |

The policies are governance records. OpenDiscoveryEngine performs detection;
the publication pipeline or export gateway enforces the action. A policy being
`Block` does not mean the scanner blocked the content by itself.

Existing installations add the classifications and policies with:

```bash
php artisan db:seed --class=PublicationDlpSeeder
```

## Detector mapping

OpenDiscoveryEngine publishes these detector keys:

- `private_ipv4`, `public_ipv4`, `internal_hostname`,
  `cloud_metadata_endpoint` → `infrastructure_metadata`
- `assessment_flag` → `assessment_answer_key`
- `private_key`, `aws_access_key`, `secret_assignment`,
  `credential_generic`, `api_key_assignment` → credential controls

OpenGRC's native scanner uses the same labels, so a finding can be measured by
identifier type even when the producer changes.

Flag prefixes are deployment-specific. The native scanner reports
`PREFIX{value}` as `assessment_answer_key` for each prefix in
`DLP_ANSWER_KEY_PREFIXES` (default `OCR,TRAILLINE`). Add every prefix the
assessment environment uses, comma-separated; an empty value disables the
detector, so it is explicit rather than a generic wildcard. Detection is a
control, not a guarantee: a flag with no configured prefix passes the scanner.

## Operating procedure

1. **Scan before publish.** Run the repository or document scan; a clean scan is
   required before a public release.
2. **Classify.** Use the highest matching data class. Answer keys and secrets are
   restricted; infrastructure metadata is confidential.
3. **Redact or replace.** Replace addresses with documentation ranges
   (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`) and flags with
   placeholders. Do not paste the real value into the finding or the ticket.
4. **Rotate secrets first.** Deleting the file does not revoke a credential.
5. **Record evidence.** Link the finding to `PUB-001` through `PUB-004` and the
   relevant control. The finding stores a preview and a digest, not the raw
   content.
6. **Check history.** A clean working tree is not enough if the value remains in
   git history. Treat history as a separate exposure and coordinate a rewrite or
   rotate the affected value.

## Review

`PUB-003` is an alert, not a pass. Review every infrastructure finding and
decide whether the address is already public, whether it changes the attack
surface, and whether the documentation needs the real value at all.

Review the policy set whenever a new publication channel, scanner, or data class
is added. The expected review interval is 90 days.
