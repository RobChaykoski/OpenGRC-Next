<?php

namespace App\Services\Dlp;

/**
 * Native, dependency-free recognisers.
 *
 * This is the baseline that always runs. It is intentionally conservative:
 * false positives are preferable to sending a credential or an HR record to an
 * external AI provider. Matched values are never returned or logged.
 */
final class RegexDlpScanner implements DlpScanner
{
    /**
     * @var array<int, array{type: string, label: string, severity: string, pattern: string}>
     */
    private const PATTERNS = [
        [
            'type' => 'credential',
            'label' => 'AWS access key',
            'severity' => 'critical',
            'pattern' => '/\bAKIA[0-9A-Z]{16}\b/',
        ],
        [
            'type' => 'credential',
            'label' => 'Private key',
            'severity' => 'critical',
            'pattern' => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----/',
        ],
        [
            'type' => 'credential',
            'label' => 'JSON Web Token',
            'severity' => 'high',
            'pattern' => '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/',
        ],
        [
            'type' => 'credential',
            'label' => 'Secret assignment',
            'severity' => 'high',
            'pattern' => '/\b(?:api[_-]?key|secret|token|password|passwd|pwd)\s*[:=]\s*["\']?[A-Za-z0-9_\-\.]{8,}/i',
        ],
        [
            'type' => 'special_category',
            'label' => 'Social insurance number',
            'severity' => 'critical',
            'pattern' => '/\b\d{3}[- ]\d{3}[- ]\d{3}\b/',
        ],
        [
            'type' => 'special_category',
            'label' => 'US social security number',
            'severity' => 'critical',
            'pattern' => '/\b\d{3}-\d{2}-\d{4}\b/',
        ],
        [
            'type' => 'special_category',
            'label' => 'Criminal or background-check data',
            'severity' => 'high',
            'pattern' => '/\b(?:criminal record|criminal history|police record|police check|background check|conviction|security clearance)\b/i',
        ],
        [
            'type' => 'special_category',
            'label' => 'Health or medical information',
            'severity' => 'high',
            'pattern' => '/\b(?:medical|diagnosis|health condition|disability|prescription|mental health)\b/i',
        ],
        [
            'type' => 'special_category',
            'label' => 'Biometric information',
            'severity' => 'high',
            'pattern' => '/\b(?:biometric|fingerprint|facial recognition|retina scan|voiceprint)\b/i',
        ],
        [
            'type' => 'special_category',
            'label' => 'Protected characteristic',
            'severity' => 'high',
            'pattern' => '/\b(?:religion|religious belief|sexual orientation|trade union|union membership|racial origin|ethnic origin|political opinion)\b/i',
        ],
        [
            'type' => 'hr',
            'label' => 'HR or personnel data',
            'severity' => 'high',
            'pattern' => '/\b(?:employee file|personnel file|performance review|disciplinary|termination|date of birth|salary|compensation|screening result)\b/i',
        ],
        [
            'type' => 'personal',
            'label' => 'Email address',
            'severity' => 'medium',
            'pattern' => '/[\w.+-]+@[\w-]+\.[\w.-]+/i',
        ],
        [
            'type' => 'personal',
            'label' => 'Phone number',
            'severity' => 'medium',
            'pattern' => '/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/',
        ],
    ];

    public function scan(string $text): array
    {
        $text = mb_substr($text, 0, 500_000);
        $matches = [];

        foreach (self::PATTERNS as $pattern) {
            $count = preg_match_all($pattern['pattern'], $text);

            if (is_int($count) && $count > 0) {
                $matches[] = new DlpMatch(
                    $pattern['type'],
                    $pattern['label'],
                    $pattern['severity'],
                    $count,
                );
            }
        }

        $cards = $this->paymentCardCount($text);

        if ($cards > 0) {
            $matches[] = new DlpMatch('personal', 'Payment card number', 'high', $cards);
        }

        return $matches;
    }

    public function name(): string
    {
        return 'regex';
    }

    private function paymentCardCount(string $text): int
    {
        if (! preg_match_all('/\b(?:\d[ -]?){13,19}\b/', $text, $matches)) {
            return 0;
        }

        $count = 0;

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate);

            if (is_string($digits) && $digits !== '' && $this->luhn($digits)) {
                $count++;
            }
        }

        return $count;
    }

    private function luhn(string $digits): bool
    {
        $sum = 0;
        $alternate = false;

        for ($index = strlen($digits) - 1; $index >= 0; $index--) {
            $digit = (int) $digits[$index];

            if ($alternate) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
