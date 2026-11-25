<?php

namespace Tests\Support;

use App\Services\Evidence\Pipelock\PipelockActionCanonicalizer;

/**
 * Build signed Pipelock ActionReceipt v1 envelopes for tests.
 *
 * Pipelock's published rotation fixtures are `g1:` session-control chains,
 * which this importer deliberately rejects, so a rotated bare-receipt chain is
 * constructed here from deterministic test keys. The published conformance
 * corpus remains the anchor for the unrotated format.
 */
final class PipelockChain
{
    /**
     * @return array{public: string, secret: string}
     */
    public static function keypair(string $label): array
    {
        $seed = hash('sha256', 'opengrc-pipelock-test-'.$label, true);
        $pair = sodium_crypto_sign_seed_keypair($seed);

        return [
            'public' => bin2hex(sodium_crypto_sign_publickey($pair)),
            'secret' => bin2hex(sodium_crypto_sign_secretkey($pair)),
        ];
    }

    /**
     * @param  array{public: string, secret: string}  $key
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function receipt(array $key, int $seq, string $prevHash, int $index, array $extra = []): array
    {
        $action = array_merge([
            'version' => 1,
            'action_id' => 'rotation-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
            'action_type' => 'read',
            'timestamp' => sprintf('2026-11-25T12:00:%02dZ', $index),
            'principal' => 'org:rotation-test',
            'actor' => 'agent:rotation-test',
            'delegation_chain' => [],
            'target' => 'https://example.test/resource',
            'side_effect_class' => 'none',
            'reversibility' => 'full',
            'policy_hash' => '',
            'verdict' => 'allow',
            'transport' => 'https',
            'method' => 'GET',
            'chain_prev_hash' => $prevHash,
            'chain_seq' => $seq,
        ], $extra);

        $canonical = (new PipelockActionCanonicalizer)->canonical($action);
        $signature = sodium_crypto_sign_detached(hash('sha256', $canonical, true), hex2bin($key['secret']));

        return [
            'version' => 1,
            'action_record' => $action,
            'signature' => 'ed25519:'.bin2hex($signature),
            'signer_key' => $key['public'],
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public static function hash(array $envelope): string
    {
        return (new PipelockActionCanonicalizer)->envelopeHash($envelope);
    }

    /**
     * A genesis segment under key A, a rotation boundary, then a segment under
     * key B.
     *
     * @return array{envelopes: array<int, array<string, mixed>>, keys: array{a: string, b: string}}
     */
    public static function rotated(int $first = 3, int $second = 2): array
    {
        $a = self::keypair('rotation-a');
        $b = self::keypair('rotation-b');

        $envelopes = [];
        $prev = 'genesis';
        $index = 0;

        for ($seq = 0; $seq < $first; $seq++) {
            $envelope = self::receipt($a, $seq, $prev, $index++);
            $envelopes[] = $envelope;
            $prev = self::hash($envelope);
        }

        $boundary = self::receipt($b, 0, $prev, $index++, [
            'key_transition' => [
                'prior_signer_key' => $a['public'],
                'prior_chain_seq' => $first - 1,
                'prior_chain_hash' => $prev,
            ],
        ]);
        $envelopes[] = $boundary;
        $prev = self::hash($boundary);

        for ($seq = 1; $seq < $second; $seq++) {
            $envelope = self::receipt($b, $seq, $prev, $index++);
            $envelopes[] = $envelope;
            $prev = self::hash($envelope);
        }

        return [
            'envelopes' => $envelopes,
            'keys' => ['a' => $a['public'], 'b' => $b['public']],
        ];
    }
}
