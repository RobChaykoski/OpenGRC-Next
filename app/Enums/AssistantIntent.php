<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The fixed questions the assistant can answer (ADR 0046).
 *
 * The list is a catalogue, not a prompt: a question maps to one deterministic,
 * read-only query. There is no free-form tool selection and no write path.
 */
enum AssistantIntent: string implements HasLabel
{
    case FrameworkGaps = 'framework_gaps';
    case OverdueTests = 'overdue_tests';
    case OpenFindings = 'open_findings';
    case RiskAppetite = 'risk_appetite';
    case ExpiringExceptions = 'expiring_exceptions';
    case PolicyAcknowledgements = 'policy_acknowledgements';
    case ControlAttestations = 'control_attestations';
    case NhiRotation = 'nhi_rotation';
    case AuditProgress = 'audit_progress';

    public function getLabel(): string
    {
        return __('assistant.intents.'.$this->value.'.label');
    }

    public function description(): string
    {
        return __('assistant.intents.'.$this->value.'.description');
    }

    public function example(): string
    {
        return __('assistant.intents.'.$this->value.'.example');
    }

    public function requiresFramework(): bool
    {
        return $this === self::FrameworkGaps;
    }

    /**
     * Phrases the router looks for. English only: the catalogue is deterministic
     * and reviewed, and a false match is worse than asking for a rephrase.
     *
     * @return array<int, string>
     */
    public function keywords(): array
    {
        return match ($this) {
            self::FrameworkGaps => [
                'gap', 'gaps', 'missing evidence', 'no evidence', 'lack evidence',
                'lacks evidence', 'without evidence', 'unreviewed', 'coverage',
                'stale evidence', 'failing validation', 'not covered',
            ],
            self::OverdueTests => [
                'overdue test', 'overdue tests', 'tests are overdue', 'test is overdue',
                'tests overdue', 'test overdue', 'testing due', 'test due',
                'retest', 'next test', 'late test',
            ],
            self::OpenFindings => [
                'finding', 'findings', 'open issue', 'open issues', 'remediation',
            ],
            self::RiskAppetite => [
                'appetite', 'above threshold', 'residual risk', 'high risk',
                'risk heat', 'risk score',
            ],
            self::ExpiringExceptions => [
                'exception', 'exceptions', 'waiver', 'waivers', 'expiring',
            ],
            self::PolicyAcknowledgements => [
                'acknowledg', 'policy coverage', 'policy attestation',
                'who has read', 'who has signed',
            ],
            self::ControlAttestations => [
                'control attestation', 'control attestations', 'owner attestation',
                'owner attestations', 'attestations are due', 'attestation is due',
                'attestations due', 'attestations overdue', 'confirm implementation',
                'implementation confirmation',
            ],
            self::NhiRotation => [
                'rotation', 'non-human', 'non human', 'service account',
                'service accounts', 'api token', 'api tokens', 'machine identity',
                'certificate', 'certificates',
            ],
            self::AuditProgress => [
                'audit', 'audits', 'fieldwork', 'items outstanding', 'audit readiness',
            ],
        };
    }
}
