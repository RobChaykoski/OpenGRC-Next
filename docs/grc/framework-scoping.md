# Framework scoping and data classification

The scoping engine turns an organisation profile into a reviewable applicability
and control matrix. It is deterministic; AI can explain the result but cannot
change it.

## Profile

```json
{
  "name": "Example Hospital",
  "industry": "healthcare",
  "country": "CA",
  "province": "ON",
  "employees": "250",
  "revenue": "50m",
  "data_classifications": ["phi", "personal_information"]
}
```

Run:

```bash
php artisan opengrc:scope profile.json --output=assessment.json
```

The output includes:

- data classifications and their level
- framework applicability, priority, confidence, and justification
- framework overlap by unified control
- unified control library
- document register with review frequency
- evidence register and source mapping
- automation candidates
- maturity roadmap
- gaps, including unknown classifications and missing profile fields

## Data classifications

Levels: Public, Internal, Confidential, Restricted.

Industry packs are seeded from `config/data_classification.php`:

| Industry | Examples |
|---|---|
| Healthcare | PHI, clinical records, health research |
| Financial services | Cardholder data, financial records, KYC/AML |
| Education | Student records, research data |
| Government | Protected A/B, CUI, law enforcement |
| Technology | Source code, customer PII, telemetry |
| Retail | Cardholder data, customer PII |
| Energy / utilities | OT data, CUI |
| Manufacturing | Intellectual property, OT data |
| Critical infrastructure | OT data, CUI |

Custom labels live in `data_classifications` and may be global or limited to an
industry. The engine refuses to apply a label that is not available for the
selected industry; it reports a gap instead.

## Boundaries

- The engine recommends; a person accepts scope and records the decision.
- Framework text is not copied. Identifiers and mappings are data, subject to the
  licence rules in `docs/OSS-POLICY.md`.
- CCM connectors (ADR 0009) consume the evidence register; they do not decide
  applicability.
