<?php

namespace App\Services\Evidence\Pipelock;

use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Verify one Pipelock ActionReceipt v1 (ADR 0060).
 *
 * Pipelock signs SHA-256(canonical action_record) with Ed25519 and puts the
 * signature in the envelope as `ed25519:<hex>`. The canonical form is Go's
 * json.Marshal of the frozen action-record struct, reproduced by
 * PipelockActionCanonicalizer.
 *
 * This verifies one signature and the record's shape. Chain linkage is replayed
 * separately by PipelockChainVerifier, because a chain is an ordered batch and
 * not a property of one receipt.
 */
final class PipelockReceiptVerifier
{
    private const ACTION_TYPES = [
        'read', 'derive', 'write', 'delegate', 'authorize',
        'spend', 'commit', 'actuate', 'unclassified',
    ];

    private const ENVELOPE_FIELDS = ['version', 'action_record', 'signature', 'signer_key'];

    public function __construct(
        protected PipelockActionCanonicalizer $canonicalizer,
    ) {}

    /**
     * @param  string|array<int, string>|null  $pinnedSignerKeys  one key, or the set of segment keys
     * @return array{
     *     valid: bool,
     *     signature: bool,
     *     pinned: bool,
     *     signer_key: string|null,
     *     canonical_sha256: string|null,
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     *     receipt: array<string, mixed>|null
     * }
     */
    public function verify(string $json, string|array|null $pinnedSignerKeys = null): array
    {
        $pinned = $this->pinnedKeys($pinnedSignerKeys);
        $isPinned = $pinned !== [];

        $errors = [];
        $warnings = [];

        try {
            $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->result(false, false, $isPinned, null, null, ['The receipt is not valid JSON.'], [], null);
        }

        if (! is_array($envelope) || array_is_list($envelope)) {
            return $this->result(false, false, $isPinned, null, null, ['The receipt must be a JSON object.'], [], null);
        }

        $unknown = array_diff(array_keys($envelope), self::ENVELOPE_FIELDS);

        if ($unknown !== []) {
            $errors[] = 'Unknown receipt envelope fields: '.implode(', ', $unknown).'.';
        }

        if (($envelope['version'] ?? null) !== 1) {
            $errors[] = 'The receipt version is not 1.';
        }

        $action = $envelope['action_record'] ?? null;

        if (! is_array($action) || array_is_list($action)) {
            $errors[] = 'action_record must be an object.';

            return $this->result(false, false, $isPinned, null, null, $errors, $warnings, null);
        }

        if (($action['version'] ?? null) !== 1) {
            $errors[] = 'The action record version is not 1.';
        }

        foreach (['action_id', 'target', 'verdict', 'transport'] as $field) {
            if (! is_string($action[$field] ?? null) || $action[$field] === '') {
                $errors[] = "{$field} is required.";
            }
        }

        $actionId = $action['action_id'] ?? null;

        if (is_string($actionId) && (strlen($actionId) > 191 || preg_match('/[\x00-\x1F\x7F]/', $actionId) === 1)) {
            $errors[] = 'action_id is too long or contains control characters.';
        }

        if (! is_string($action['action_type'] ?? null) || ! in_array($action['action_type'], self::ACTION_TYPES, true)) {
            $errors[] = 'action_type is not recognised.';
        }

        try {
            $canonical = $this->canonicalizer->canonical($action);
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();

            return $this->result(false, false, $isPinned, null, null, $errors, $warnings, null);
        }

        $digest = hash('sha256', $canonical, true);
        $signature = $envelope['signature'] ?? null;
        $signerKey = $envelope['signer_key'] ?? null;

        if (! is_string($signature) || ! str_starts_with($signature, 'ed25519:')) {
            $errors[] = 'The signature is missing the ed25519: prefix.';

            return $this->result(false, false, $isPinned, is_string($signerKey) ? $signerKey : null, bin2hex($digest), $errors, $warnings, $action);
        }

        $signatureHex = substr($signature, strlen('ed25519:'));

        if (strlen($signatureHex) !== 128 || ctype_xdigit($signatureHex) !== true) {
            $errors[] = 'The signature is not 64 bytes of hex.';

            return $this->result(false, false, $isPinned, is_string($signerKey) ? $signerKey : null, bin2hex($digest), $errors, $warnings, $action);
        }

        if (! is_string($signerKey) || strlen($signerKey) !== 64 || ctype_xdigit($signerKey) !== true) {
            $errors[] = 'signer_key is not 32 bytes of hex.';

            return $this->result(false, false, $isPinned, is_string($signerKey) ? $signerKey : null, bin2hex($digest), $errors, $warnings, $action);
        }

        $signerKey = strtolower($signerKey);

        if ($isPinned) {
            foreach ($pinned as $pinnedKey) {
                if (strlen($pinnedKey) !== 64 || ctype_xdigit($pinnedKey) !== true) {
                    $errors[] = 'A pinned signer key is not 32 bytes of hex.';

                    return $this->result(false, false, true, $signerKey, bin2hex($digest), $errors, $warnings, $action);
                }
            }

            if (! in_array($signerKey, $pinned, true)) {
                $errors[] = 'The receipt was not signed by a pinned key.';

                return $this->result(false, false, true, $signerKey, bin2hex($digest), $errors, $warnings, $action);
            }
        } else {
            $warnings[] = 'The signature was checked against the receipt inline signer_key. Pin every segment key for non-repudiation.';
        }

        try {
            $validSignature = sodium_crypto_sign_verify_detached(
                hex2bin($signatureHex),
                $digest,
                hex2bin($signerKey),
            );
        } catch (Throwable) {
            $validSignature = false;
        }

        if (! $validSignature) {
            $errors[] = 'The Ed25519 signature is invalid.';
        }

        return $this->result(
            $errors === [],
            $validSignature,
            $isPinned,
            $signerKey,
            bin2hex($digest),
            $errors,
            $warnings,
            $action,
        );
    }

    /**
     * @param  string|array<int, string>|null  $keys
     * @return array<int, string>
     */
    private function pinnedKeys(string|array|null $keys): array
    {
        if ($keys === null) {
            return [];
        }

        $normalised = [];

        foreach (is_array($keys) ? $keys : [$keys] as $key) {
            if (! is_string($key)) {
                continue;
            }

            foreach (preg_split('/[\s,]+/', $key) ?: [] as $part) {
                $part = strtolower(trim($part));

                if ($part !== '') {
                    $normalised[$part] = true;
                }
            }
        }

        return array_keys($normalised);
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>|null  $action
     * @return array{
     *     valid: bool,
     *     signature: bool,
     *     pinned: bool,
     *     signer_key: string|null,
     *     canonical_sha256: string|null,
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     *     receipt: array<string, mixed>|null
     * }
     */
    private function result(
        bool $valid,
        bool $signature,
        bool $pinned,
        ?string $signerKey,
        ?string $canonicalSha256,
        array $errors,
        array $warnings,
        ?array $action,
    ): array {
        return [
            'valid' => $valid,
            'signature' => $signature,
            'pinned' => $pinned,
            'signer_key' => $signerKey,
            'canonical_sha256' => $canonicalSha256,
            'errors' => $errors,
            'warnings' => $warnings,
            'receipt' => $action,
        ];
    }
}
