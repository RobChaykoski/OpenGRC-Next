<?php

namespace Tests\Unit\Evidence;

use App\Services\Evidence\Pipelock\PipelockActionCanonicalizer;
use App\Services\Evidence\Pipelock\PipelockChainVerifier;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PipelockChainVerifierTest extends TestCase
{
    #[Test]
    public function the_published_chain_verifies_from_genesis(): void
    {
        $envelopes = $this->chain();
        $result = $this->verifier()->verify($envelopes);

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['complete']);
        $this->assertSame('genesis', $result['scope']);
        $this->assertSame(4, $result['links']);
        $this->assertSame(1, $result['segments']);
        $this->assertSame(4, $result['final_seq']);
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame('be904bd5ca82adc26c2969872c23925f22ff24e33faf44a1185b9ffc0e2c2b5a', $result['head_hash']);

        foreach (range(1, count($envelopes) - 1) as $index) {
            $this->assertSame(
                $envelopes[$index]['action_record']['chain_prev_hash'],
                $result['hashes'][$index - 1],
                'The recorded hash must be the next receipt\'s chain_prev_hash.',
            );
        }
    }

    #[Test]
    public function a_single_genesis_receipt_is_a_complete_chain(): void
    {
        $result = $this->verifier()->verify([$this->chain()[0]]);

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['complete']);
        $this->assertSame(0, $result['links']);
        $this->assertSame(0, $result['final_seq']);
        $this->assertSame('9994a67b9cb324bae8d648b5baccd5d5403600b2bc5047a40d0355a610f2dc44', $result['head_hash']);
    }

    #[Test]
    public function the_published_broken_chain_reports_the_break_at_seq_three(): void
    {
        $result = $this->verifier()->verify($this->chain('broken-chain'));

        $this->assertFalse($result['verified']);
        $this->assertFalse($result['complete']);
        $this->assertSame('none', $result['scope']);
        $this->assertNull($result['head_hash']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('breaks at chain_seq 3', $result['errors'][0]);
    }

    #[Test]
    public function a_sequence_gap_is_rejected(): void
    {
        $envelopes = $this->chain();

        $result = $this->verifier()->verify([$envelopes[0], $envelopes[1], $envelopes[3]]);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('breaks at chain_seq 3: expected chain_seq 2', $result['errors'][0]);
        $this->assertSame(1, $result['links']);
    }

    #[Test]
    public function an_out_of_order_batch_is_rejected(): void
    {
        $envelopes = $this->chain();

        $result = $this->verifier()->verify([$envelopes[0], $envelopes[2], $envelopes[1]]);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('breaks at chain_seq 2: expected chain_seq 1', $result['errors'][0]);
    }

    #[Test]
    public function a_seq_zero_receipt_that_does_not_start_at_genesis_is_rejected(): void
    {
        $envelope = $this->chain()[0];
        $envelope['action_record']['chain_prev_hash'] = 'sha256:deadbeef';

        $result = $this->verifier()->verify([$envelope], true);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('chain_prev_hash is not genesis', $result['errors'][0]);
    }

    #[Test]
    public function a_mid_chain_slice_is_refused_without_the_opt_in(): void
    {
        $envelopes = $this->chain();

        $result = $this->verifier()->verify([$envelopes[2], $envelopes[3], $envelopes[4]]);

        $this->assertFalse($result['verified']);
        $this->assertFalse($result['complete']);
        $this->assertStringContainsString('does not start at genesis', $result['errors'][0]);
        $this->assertStringContainsString('--allow-partial-chain', $result['errors'][0]);
    }

    #[Test]
    public function a_mid_chain_slice_verifies_its_links_when_partial_is_allowed(): void
    {
        $envelopes = $this->chain();

        $result = $this->verifier()->verify([$envelopes[2], $envelopes[3], $envelopes[4]], true);

        $this->assertTrue($result['verified']);
        $this->assertFalse($result['complete']);
        $this->assertSame('partial', $result['scope']);
        $this->assertSame(2, $result['links']);
        $this->assertSame(4, $result['final_seq']);
        $this->assertSame([], $result['errors']);
        $this->assertStringContainsString('starts at chain_seq 2', $result['warnings'][0]);
    }

    #[Test]
    public function a_tampered_link_inside_a_partial_slice_is_still_rejected(): void
    {
        $envelopes = $this->chain();
        $envelopes[4]['action_record']['chain_prev_hash'] = 'sha256:deadbeef';

        $result = $this->verifier()->verify([$envelopes[2], $envelopes[3], $envelopes[4]], true);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('breaks at chain_seq 4', $result['errors'][0]);
    }

    #[Test]
    public function a_receipt_from_another_signer_is_not_spliced_in(): void
    {
        $envelopes = $this->chain();
        $envelopes[1]['signer_key'] = str_repeat('b', 64);

        $result = $this->verifier()->verify($envelopes);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('changes signer_key at chain_seq 1', $result['errors'][0]);
    }

    #[Test]
    public function a_chain_that_starts_at_a_rotation_segment_is_rejected(): void
    {
        $envelope = $this->chain()[0];
        $envelope['action_record']['key_transition'] = [
            'prior_signer_key' => str_repeat('a', 64),
            'prior_chain_seq' => 7,
            'prior_chain_hash' => str_repeat('b', 64),
        ];

        $result = $this->verifier()->verify([$envelope]);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('starts at a signing-key rotation', $result['errors'][0]);
    }

    #[Test]
    public function an_empty_batch_is_rejected(): void
    {
        $result = $this->verifier()->verify([]);

        $this->assertFalse($result['verified']);
        $this->assertSame('none', $result['scope']);
        $this->assertSame([], $result['hashes']);
        $this->assertSame(['No receipts were supplied to the chain verifier.'], $result['errors']);
    }

    private function verifier(): PipelockChainVerifier
    {
        return new PipelockChainVerifier(new PipelockActionCanonicalizer);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function chain(string $name = 'valid-chain'): array
    {
        $contents = (string) file_get_contents(base_path("tests/Fixtures/pipelock/{$name}.jsonl"));
        $envelopes = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $this->fail('The Pipelock chain fixture is not valid JSONL: '.$exception->getMessage());
            }

            if (is_array($decoded)) {
                $envelopes[] = $decoded;
            }
        }

        return $envelopes;
    }
}
