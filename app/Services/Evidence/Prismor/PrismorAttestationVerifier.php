<?php

namespace App\Services\Evidence\Prismor;

use JsonException;
use stdClass;
use Throwable;

/**
 * Verify a Prismor signed attestation bundle (prismor.attestation.v1).
 *
 * The bundle body is canonicalized with RFC 8785 (JCS) and hashed with SHA-256.
 * The Ed25519 signature is over {hash, ts, identity}, where `hash` is the
 * content hash and the identity fields are the top-level bundle fields. The
 * envelope fields (content_hash, signature, signing_*) are not part of the
 * body.
 *
 * Objects and arrays are kept distinct while canonicalizing: an empty object
 * must remain `{}` and an empty array `[]`. Decoding to stdClass preserves
 * that distinction.
 */
final class PrismorAttestationVerifier
{
    public const SCHEMA = 'prismor.attestation.v1';

    private const ENVELOPE = [
        'content_hash',
        'signature',
        'signing_alg',
        'signing_pubkey',
        'signing_key_id',
    ];

    /**
     * @return array{
     *     valid: bool,
     *     content_hash: bool,
     *     signature: bool,
     *     pinned: bool,
     *     key_id: string|null,
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     *     bundle: array<string, mixed>|null
     * }
     */
    public function verify(string $json, ?string $pinnedPublicKey = null): array
    {
        $errors = [];
        $warnings = [];

        try {
            $bundle = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->result(false, false, false, $pinnedPublicKey !== null, null, ['The attestation is not valid JSON.'], [], null);
        }

        if (! $bundle instanceof stdClass) {
            return $this->result(false, false, false, $pinnedPublicKey !== null, null, ['The attestation must be a JSON object.'], [], null);
        }

        if (($bundle->schema ?? null) !== self::SCHEMA) {
            $errors[] = 'The schema is not '.self::SCHEMA.'.';
        }

        $contentHash = is_string($bundle->content_hash ?? null)
            ? $bundle->content_hash
            : null;

        $body = clone $bundle;

        foreach (self::ENVELOPE as $field) {
            unset($body->{$field});
        }

        $recomputed = hash('sha256', self::canonical($body));
        $contentHashOk = $contentHash !== null && hash_equals($recomputed, $contentHash);

        if (! $contentHashOk) {
            $errors[] = 'The content hash does not match the bundle body.';
        }

        $signatureOk = $this->verifySignature($bundle, $contentHash, $pinnedPublicKey, $errors, $warnings);

        if (! $signatureOk) {
            $errors[] = 'The Ed25519 signature is invalid or missing.';
        }

        $decoded = json_decode($json, true, 512);

        return $this->result(
            $contentHashOk && $signatureOk && $errors === [],
            $contentHashOk,
            $signatureOk,
            $pinnedPublicKey !== null,
            is_string($bundle->signing_key_id ?? null) ? $bundle->signing_key_id : null,
            $errors,
            $warnings,
            is_array($decoded) ? $decoded : null,
        );
    }

    /**
     * RFC 8785 subset used by Prismor: sorted object keys, no whitespace,
     * UTF-8, unescaped Unicode and slashes. Objects and arrays are distinct.
     */
    public static function canonical(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_string($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            $parts = [];

            foreach ($properties as $key => $item) {
                $parts[] = self::canonical((string) $key).':'.self::canonical($item);
            }

            return '{'.implode(',', $parts).'}';
        }

        if (is_array($value)) {
            return '['.implode(',', array_map(self::canonical(...), $value)).']';
        }

        throw new \InvalidArgumentException('Unsupported value in attestation body.');
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    private function verifySignature(
        stdClass $bundle,
        ?string $contentHash,
        ?string $pinnedPublicKey,
        array &$errors,
        array &$warnings,
    ): bool {
        $signature = $bundle->signature ?? null;
        $publicKey = $bundle->signing_pubkey ?? null;
        $keyId = $bundle->signing_key_id ?? null;
        $algorithm = $bundle->signing_alg ?? null;

        if (! is_string($signature) || ! is_string($publicKey) || ! is_string($keyId)) {
            $warnings[] = 'The bundle is unsigned.';

            return false;
        }

        if ($algorithm !== 'ed25519') {
            $errors[] = 'The signing algorithm is not ed25519.';

            return false;
        }

        $signatureBytes = base64_decode($signature, true);
        $publicKeyBytes = base64_decode($publicKey, true);

        if ($signatureBytes === false || strlen($signatureBytes) !== SODIUM_CRYPTO_SIGN_BYTES) {
            $errors[] = 'The signature is not a 64-byte base64 value.';

            return false;
        }

        if ($publicKeyBytes === false || strlen($publicKeyBytes) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $errors[] = 'The signing public key is not a 32-byte base64 value.';

            return false;
        }

        $effectiveKey = $publicKeyBytes;

        if ($pinnedPublicKey !== null) {
            $pinned = base64_decode($pinnedPublicKey, true);

            if ($pinned === false || strlen($pinned) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $errors[] = 'The pinned public key is not a 32-byte Ed25519 key.';

                return false;
            }

            if (! hash_equals($pinned, $publicKeyBytes)) {
                $errors[] = 'The signature was not produced by the pinned key.';

                return false;
            }

            $effectiveKey = $pinned;
        } else {
            $warnings[] = 'The signature was checked against the bundle inline key. Pin the key for non-repudiation.';
        }

        $computedKeyId = substr(hash('sha256', $effectiveKey), 0, 16);

        if (! hash_equals($computedKeyId, $keyId)) {
            $errors[] = 'The signing key id does not match the public key.';

            return false;
        }

        $subject = ($bundle->subject ?? null) instanceof stdClass ? $bundle->subject : null;

        $payload = (object) [
            'hash' => $contentHash,
            'ts' => $bundle->ts ?? null,
            'identity' => (object) [
                'device_id' => $bundle->device_id ?? null,
                'agent' => $bundle->agent ?? null,
                'agent_name' => $bundle->agent_name ?? null,
                'subagent_id' => $bundle->subagent_id ?? null,
                'user_id' => $subject?->user_id,
                'team_id' => $subject?->team_id,
                'org_id' => $subject?->org_id,
            ],
        ];

        try {
            return sodium_crypto_sign_verify_detached($signatureBytes, self::canonical($payload), $effectiveKey);
        } catch (Throwable) {
            $errors[] = 'The signature could not be verified.';

            return false;
        }
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>|null  $bundle
     * @return array{
     *     valid: bool,
     *     content_hash: bool,
     *     signature: bool,
     *     pinned: bool,
     *     key_id: string|null,
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     *     bundle: array<string, mixed>|null
     * }
     */
    private function result(
        bool $valid,
        bool $contentHash,
        bool $signature,
        bool $pinned,
        ?string $keyId,
        array $errors,
        array $warnings,
        ?array $bundle,
    ): array {
        return [
            'valid' => $valid,
            'content_hash' => $contentHash,
            'signature' => $signature,
            'pinned' => $pinned,
            'key_id' => $keyId,
            'errors' => $errors,
            'warnings' => $warnings,
            'bundle' => $bundle,
        ];
    }
}
