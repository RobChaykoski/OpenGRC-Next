<?php

namespace App\Services\Evidence\Prismor;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Verify a Prismor signed telemetry receipt without importing Prismor.
 *
 * The receipt has two independent integrity layers (Prismor's
 * docs/telemetry-receipts.md):
 *
 * 1. A keyless SHA-256 chain hash over seven normalised fields plus `seq` and
 *    `prev_hash`. It catches a changed verdict, rule, tool or evidence hash.
 * 2. An Ed25519 signature over `{hash, ts, identity}`. It binds the chain hash
 *    to the device, agent, subject and timestamp. Verifying against the
 *    receipt's own inline key proves only internal consistency; a pinned key is
 *    what gives the signature teeth.
 *
 * Canonical JSON is UTF-8, sorted keys, no whitespace, unescaped Unicode and
 * unescaped slashes. That matches Python's
 * `json.dumps(payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False)`.
 */
final class PrismorReceiptVerifier
{
    private const VERDICTS = ['blocked', 'warned', 'observed', 'allowed'];

    private const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    private const STRING_CAPS = [
        'event_id' => 60,
        'rule_id' => 80,
        'tool_name' => 80,
        'evidence_hash' => 64,
        'session_id' => 80,
    ];

    /**
     * @param  array<string, mixed>  $receipt
     * @return array{
     *     valid: bool,
     *     integrity: bool,
     *     signature: bool,
     *     pinned: bool,
     *     key_id: string|null,
     *     chain_hash: string|null,
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     *     receipt: array<string, mixed>|null
     * }
     */
    public function verify(array $receipt, ?string $pinnedPublicKey = null): array
    {
        $errors = [];
        $warnings = [];
        $normalized = $this->normalize($receipt, $errors);

        if ($normalized === null) {
            return [
                'valid' => false,
                'integrity' => false,
                'signature' => false,
                'pinned' => $pinnedPublicKey !== null,
                'key_id' => null,
                'chain_hash' => null,
                'errors' => $errors,
                'warnings' => $warnings,
                'receipt' => null,
            ];
        }

        $chainHash = $this->chainHash($normalized);
        $integrity = is_string($normalized['hash']) && hash_equals($chainHash, $normalized['hash']);

        if (! $integrity) {
            $errors[] = 'The chain hash does not match the receipt fields.';
        }

        $signature = $this->verifySignature($normalized, $pinnedPublicKey, $errors, $warnings);

        if (! $signature) {
            $errors[] = 'The Ed25519 signature is invalid.';
        }

        return [
            'valid' => $integrity && $signature && $errors === [],
            'integrity' => $integrity,
            'signature' => $signature,
            'pinned' => $pinnedPublicKey !== null,
            'key_id' => $normalized['signing_key_id'],
            'chain_hash' => $chainHash,
            'errors' => $errors,
            'warnings' => $warnings,
            'receipt' => $normalized,
        ];
    }

    /**
     * Canonical JSON for both integrity layers. Public so a test or a second
     * verifier can reproduce the bytes without importing this class.
     */
    public static function canonical(mixed $value): string
    {
        self::sortKeys($value);

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $receipt
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function normalize(array $receipt, array &$errors): ?array
    {
        $eventId = $this->requiredString($receipt['event_id'] ?? null, self::STRING_CAPS['event_id'], $errors, 'event_id');
        $verdict = $this->enumValue($receipt['verdict'] ?? null, self::VERDICTS, $errors, 'verdict');
        $severity = $this->enumValue($receipt['severity'] ?? null, self::SEVERITIES, $errors, 'severity');

        $ts = $receipt['ts'] ?? null;

        if (! is_string($ts) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/', $ts) !== 1) {
            $errors[] = 'ts must be an ISO-8601 timestamp with an offset.';
            $ts = null;
        }

        $tsAt = null;

        if (is_string($ts)) {
            try {
                $tsAt = CarbonImmutable::parse($ts)->utc();
            } catch (Throwable) {
                $errors[] = 'ts is not a valid timestamp.';
            }
        }

        $deviceId = $this->identityString($receipt['device_id'] ?? null, $errors, 'device_id', true);
        $agent = $this->identityString($receipt['agent'] ?? null, $errors, 'agent', true);
        $agentName = $this->identityString($receipt['agent_name'] ?? null, $errors, 'agent_name');
        $subagentId = $this->identityString($receipt['subagent_id'] ?? null, $errors, 'subagent_id');

        $subject = null;

        if (isset($receipt['subject']) && $receipt['subject'] !== []) {
            if (! is_array($receipt['subject'])) {
                $errors[] = 'subject must be an object or null.';
            } else {
                $subject = [
                    'user_id' => $this->identityString($receipt['subject']['user_id'] ?? null, $errors, 'subject.user_id'),
                    'team_id' => $this->identityString($receipt['subject']['team_id'] ?? null, $errors, 'subject.team_id'),
                    'org_id' => $this->identityString($receipt['subject']['org_id'] ?? null, $errors, 'subject.org_id'),
                ];
            }
        }

        $seq = $receipt['seq'] ?? null;

        if (! is_int($seq) || $seq < 0) {
            $errors[] = 'seq must be a non-negative integer.';
            $seq = 0;
        }

        $prevHash = $this->hashValue($receipt['prev_hash'] ?? null, $errors, 'prev_hash');
        $hash = $this->hashValue($receipt['hash'] ?? null, $errors, 'hash');
        $signature = $this->base64Value($receipt['signature'] ?? null, 64, $errors, 'signature');
        $pubkey = $this->base64Value($receipt['signing_pubkey'] ?? null, 32, $errors, 'signing_pubkey');
        $keyId = $this->keyIdValue($receipt['signing_key_id'] ?? null, $errors);

        $algorithm = $receipt['signing_alg'] ?? null;

        if (! is_string($algorithm) || strtolower($algorithm) !== 'ed25519') {
            $errors[] = 'signing_alg must be ed25519.';
        }

        $ruleId = $this->stringValue($receipt['rule_id'] ?? null, self::STRING_CAPS['rule_id'], $errors, 'rule_id');
        $toolName = $this->stringValue($receipt['tool_name'] ?? null, self::STRING_CAPS['tool_name'], $errors, 'tool_name');
        $evidenceHash = $this->stringValue($receipt['evidence_hash'] ?? null, self::STRING_CAPS['evidence_hash'], $errors, 'evidence_hash');
        $sessionId = $this->stringValue($receipt['session_id'] ?? null, self::STRING_CAPS['session_id'], $errors, 'session_id');

        if ($errors !== []) {
            return null;
        }

        return [
            'event_id' => $eventId,
            'verdict' => $verdict,
            'severity' => $severity,
            'rule_id' => $ruleId,
            'tool_name' => $toolName,
            'evidence_hash' => $evidenceHash,
            'session_id' => $sessionId,
            'ts' => $ts,
            'ts_at' => $tsAt,
            'device_id' => $deviceId,
            'agent' => $agent,
            'agent_name' => $agentName,
            'subagent_id' => $subagentId,
            'subject' => $subject,
            'seq' => $seq,
            'prev_hash' => $prevHash,
            'hash' => $hash,
            'signature' => $signature,
            'signing_alg' => 'ed25519',
            'signing_pubkey' => $pubkey,
            'signing_key_id' => $keyId,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function chainHash(array $normalized): string
    {
        $payload = [
            'event_id' => $normalized['event_id'],
            'verdict' => $normalized['verdict'],
            'severity' => $normalized['severity'],
            'rule_id' => $normalized['rule_id'],
            'tool_name' => $normalized['tool_name'],
            'evidence_hash' => $normalized['evidence_hash'],
            'session_id' => $normalized['session_id'],
            'seq' => $normalized['seq'],
            'prev_hash' => $normalized['prev_hash'],
        ];

        return hash('sha256', self::canonical($payload));
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    private function verifySignature(
        array $normalized,
        ?string $pinnedPublicKey,
        array &$errors,
        array &$warnings,
    ): bool {
        $signature = base64_decode((string) $normalized['signature'], true);
        $receiptKey = base64_decode((string) $normalized['signing_pubkey'], true);

        if ($signature === false || $receiptKey === false) {
            $errors[] = 'The signature or public key is not valid base64.';

            return false;
        }

        $effectiveKey = $receiptKey;

        if ($pinnedPublicKey !== null) {
            $pinned = base64_decode($pinnedPublicKey, true);

            if ($pinned === false || strlen($pinned) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $errors[] = 'The pinned public key is not a 32-byte Ed25519 key.';

                return false;
            }

            if (! hash_equals($pinned, $receiptKey)) {
                $errors[] = 'The receipt signing key does not match the pinned key.';

                return false;
            }

            $effectiveKey = $pinned;
        } else {
            $warnings[] = 'The signature was checked against the receipt’s inline key. Pin the key for non-repudiation.';
        }

        $computedKeyId = substr(hash('sha256', $effectiveKey), 0, 16);

        if (! hash_equals($computedKeyId, (string) $normalized['signing_key_id'])) {
            $errors[] = 'The signing key id does not match the public key.';

            return false;
        }

        $payload = self::canonical([
            'hash' => $normalized['hash'],
            'ts' => $normalized['ts'],
            'identity' => [
                'device_id' => $normalized['device_id'],
                'agent' => $normalized['agent'],
                'agent_name' => $normalized['agent_name'],
                'subagent_id' => $normalized['subagent_id'],
                'user_id' => $normalized['subject']['user_id'] ?? null,
                'team_id' => $normalized['subject']['team_id'] ?? null,
                'org_id' => $normalized['subject']['org_id'] ?? null,
            ],
        ]);

        try {
            return sodium_crypto_sign_verify_detached($signature, $payload, $effectiveKey);
        } catch (Throwable) {
            $errors[] = 'The signature could not be verified.';

            return false;
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function stringValue(mixed $value, int $cap, array &$errors, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            $errors[] = "{$field} must be a string.";

            return null;
        }

        return mb_substr($value, 0, $cap);
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function requiredString(mixed $value, int $cap, array &$errors, string $field): ?string
    {
        $normalized = $this->stringValue($value, $cap, $errors, $field);

        if ($normalized === null) {
            $errors[] = "{$field} is required.";
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function identityString(mixed $value, array &$errors, string $field, bool $required = false): ?string
    {
        if ($value === null) {
            if ($required) {
                $errors[] = "{$field} is required.";
            }

            return null;
        }

        if (! is_string($value)) {
            $errors[] = "{$field} must be a string.";

            return null;
        }

        if ($value === '' && $required) {
            $errors[] = "{$field} is required.";

            return null;
        }

        // Identity fields are part of the signature payload, so they are not
        // truncated. An over-long value is rejected instead of silently signed
        // over different bytes.
        if (mb_strlen($value) > 255) {
            $errors[] = "{$field} exceeds 255 characters.";

            return null;
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, string>  $errors
     */
    private function enumValue(mixed $value, array $values, array &$errors, string $field): ?string
    {
        if (! is_string($value)) {
            $errors[] = "{$field} is required.";

            return null;
        }

        $normalized = strtolower(mb_substr($value, 0, 16));

        if (! in_array($normalized, $values, true)) {
            $errors[] = "{$field} is not a recognised value.";

            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function hashValue(mixed $value, array &$errors, string $field): ?string
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            $errors[] = "{$field} must be 64 lowercase hex characters.";

            return null;
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function base64Value(mixed $value, int $bytes, array &$errors, string $field): ?string
    {
        if (! is_string($value)) {
            $errors[] = "{$field} is required.";

            return null;
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false || strlen($decoded) !== $bytes) {
            $errors[] = "{$field} must be {$bytes} bytes of base64.";

            return null;
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function keyIdValue(mixed $value, array &$errors): ?string
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{16}$/', $value) !== 1) {
            $errors[] = 'signing_key_id must be 16 lowercase hex characters.';

            return null;
        }

        return $value;
    }

    private static function sortKeys(mixed &$value): void
    {
        if (! is_array($value)) {
            return;
        }

        ksort($value, SORT_STRING);

        foreach ($value as &$item) {
            self::sortKeys($item);
        }
    }
}
