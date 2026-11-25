<?php

namespace App\Services\Evidence\Pipelock;

use InvalidArgumentException;

/**
 * Replay a Pipelock ActionReceipt v1 chain (ADR 0060).
 *
 * Pipelock links the receipts a writer emits into a hash chain: `chain_seq`
 * starts at 0 and increments by one, and `chain_prev_hash` is the hex SHA-256
 * of the previous receipt's canonical envelope, or the literal `genesis` for
 * the first receipt. A rotated signing key opens a new segment at `chain_seq` 0
 * with a `key_transition` marker naming the prior key, sequence and tail hash.
 * The batch must arrive in chain order, and this walks it recomputing every
 * link.
 *
 * The marker is continuity metadata, not trust delegation: it is signed by the
 * new key, so an attacker who can write the file could mint a consistent one.
 * Trust comes only from the importer's pinned key set, which verifies every
 * receipt against a key the operator supplied.
 *
 * What a verified chain proves: the receipts present are the ones the signer
 * wrote, in order, from genesis, and none was inserted, reordered or edited.
 * What it does not prove: that the last receipt is the run's last receipt. A
 * chain has no close record here, so a truncated export is indistinguishable
 * from a shorter run. Chain replay also does not cross a signing-key rotation;
 * each key segment is exported and pinned separately.
 */
final class PipelockChainVerifier
{
    private const GENESIS = 'genesis';

    private const SESSION_OPEN_PREFIX = 'g1:';

    public function __construct(
        protected PipelockActionCanonicalizer $canonicalizer,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $envelopes  receipts in file order
     * @return array{
     *     verified: bool,
     *     complete: bool,
     *     scope: 'genesis'|'partial'|'none',
     *     links: int,
     *     segments: int,
     *     final_seq: int|null,
     *     head_hash: string|null,
     *     hashes: array<int, string>,
     *     errors: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    public function verify(array $envelopes, bool $allowPartial = false): array
    {
        if ($envelopes === []) {
            return $this->result(false, false, 'none', 0, 0, null, null, [], ['No receipts were supplied to the chain verifier.'], []);
        }

        $errors = [];
        $warnings = [];
        $hashes = [];
        $links = 0;
        $segments = 0;
        $complete = false;
        $finalSeq = null;
        $head = null;
        $previousSeq = null;
        $headSigner = '';

        foreach ($envelopes as $index => $envelope) {
            $number = $index + 1;
            $action = is_array($envelope['action_record'] ?? null) ? $envelope['action_record'] : [];

            try {
                $hash = $this->canonicalizer->envelopeHash($envelope);
            } catch (InvalidArgumentException $exception) {
                $errors[] = "Receipt {$number}: ".$exception->getMessage();

                break;
            }

            $seq = $action['chain_seq'] ?? null;
            $prev = $action['chain_prev_hash'] ?? null;

            if (! is_int($seq) || $seq < 0) {
                $errors[] = "Receipt {$number}: chain_seq is missing or not a non-negative integer.";

                break;
            }

            if (! is_string($prev) || $prev === '') {
                $errors[] = "Receipt {$number}: chain_prev_hash is missing.";

                break;
            }

            $signerKey = is_string($envelope['signer_key'] ?? null) ? strtolower($envelope['signer_key']) : '';
            $marker = $action['key_transition'] ?? null;

            if ($index === 0) {
                if ($marker !== null) {
                    $errors[] = 'The chain starts at a signing-key rotation, so its ancestry is not in the batch.';

                    break;
                }

                if ($seq === 0 && $prev === self::GENESIS) {
                    $complete = true;
                } elseif ($seq !== 0) {
                    if (! $allowPartial) {
                        $errors[] = "The receipt chain does not start at genesis: the first receipt is chain_seq {$seq}. Import the whole run, or pass --allow-partial-chain to accept a mid-chain slice that cannot be chain-verified.";

                        break;
                    }

                    $warnings[] = "The receipt chain starts at chain_seq {$seq}. Its links are verified from the first receipt present, but its ancestry back to genesis is not; the imported records are marked chain_verified: false.";
                } elseif (str_starts_with($prev, self::SESSION_OPEN_PREFIX)) {
                    $errors[] = 'The chain starts at a bound session_open genesis, which this importer does not support. Export the bare action receipts instead.';

                    break;
                } else {
                    $errors[] = 'The first receipt is chain_seq 0 but chain_prev_hash is not genesis. The batch is forked or forged.';

                    break;
                }

                $segments = 1;
                $headSigner = $signerKey;
            } elseif ($marker !== null) {
                $rotation = $this->rotationError($action, $seq, $prev, $signerKey, $head, $headSigner, $previousSeq);

                if ($rotation !== null) {
                    $errors[] = $rotation;

                    break;
                }

                $segments++;
                $headSigner = $signerKey;
                $previousSeq = 0;
                $hashes[$index] = $hash;
                $finalSeq = 0;
                $head = $hash;

                continue;
            } elseif ($signerKey !== $headSigner) {
                $errors[] = "The receipt chain changes signer_key at chain_seq {$seq} without a rotation marker. Receipts from another signer are not spliced into a chain.";

                break;
            } elseif ($seq !== $previousSeq + 1) {
                $errors[] = "The receipt chain breaks at chain_seq {$seq}: expected chain_seq ".($previousSeq + 1).'.';

                break;
            } elseif ($prev !== $head) {
                $errors[] = "The receipt chain breaks at chain_seq {$seq}: chain_prev_hash does not match the previous receipt.";

                break;
            } else {
                $links++;
            }

            $hashes[$index] = $hash;
            $previousSeq = $seq;
            $finalSeq = $seq;
            $head = $hash;
        }

        if ($errors !== []) {
            return $this->result(false, false, 'none', $links, $segments, null, null, $hashes, $errors, $warnings);
        }

        return $this->result(
            true,
            $complete,
            $complete ? 'genesis' : 'partial',
            $links,
            $segments,
            $finalSeq,
            $head,
            $hashes,
            [],
            $warnings,
        );
    }

    /**
     * Validate a signing-key rotation marker against the segment it closes.
     *
     * @param  array<string, mixed>  $action
     */
    private function rotationError(
        array $action,
        int $seq,
        string $prev,
        string $signerKey,
        ?string $head,
        string $headSigner,
        ?int $previousSeq,
    ): ?string {
        $marker = $action['key_transition'] ?? null;

        if (! is_array($marker) || array_is_list($marker)) {
            return 'The signing-key rotation marker is not an object.';
        }

        if ($seq !== 0) {
            return "The signing-key rotation marker is on chain_seq {$seq}; a rotated segment must start at chain_seq 0.";
        }

        $priorHash = is_string($marker['prior_chain_hash'] ?? null) ? strtolower($marker['prior_chain_hash']) : '';
        $priorSigner = is_string($marker['prior_signer_key'] ?? null) ? strtolower($marker['prior_signer_key']) : '';

        if ($priorHash !== $head || $prev !== $head) {
            return 'The signing-key rotation does not reference the prior tail. The batch is spliced or edited.';
        }

        if ($priorSigner !== $headSigner) {
            return 'The signing-key rotation does not name the prior segment signer.';
        }

        if ($signerKey === $headSigner) {
            return 'The signing-key rotation does not change the signer key.';
        }

        if (($marker['prior_chain_seq'] ?? null) !== $previousSeq) {
            return 'The signing-key rotation prior_chain_seq does not match the prior segment tail.';
        }

        return null;
    }

    /**
     * @param  'genesis'|'partial'|'none'  $scope
     * @param  array<int, string>  $hashes
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @return array{
     *     verified: bool,
     *     complete: bool,
     *     scope: 'genesis'|'partial'|'none',
     *     links: int,
     *     segments: int,
     *     final_seq: int|null,
     *     head_hash: string|null,
     *     hashes: array<int, string>,
     *     errors: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    private function result(
        bool $verified,
        bool $complete,
        string $scope,
        int $links,
        int $segments,
        ?int $finalSeq,
        ?string $headHash,
        array $hashes,
        array $errors,
        array $warnings,
    ): array {
        return [
            'verified' => $verified,
            'complete' => $complete,
            'scope' => $scope,
            'links' => $links,
            'segments' => $segments,
            'final_seq' => $finalSeq,
            'head_hash' => $headHash,
            'hashes' => $hashes,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
