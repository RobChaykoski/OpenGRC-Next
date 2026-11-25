<?php

namespace Tests\Unit\Evidence;

use App\Services\Evidence\Pipelock\PipelockActionCanonicalizer;
use App\Services\Evidence\Pipelock\PipelockChainVerifier;
use App\Services\Evidence\Pipelock\PipelockReceiptVerifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PipelockChain;
use Tests\TestCase;

class PipelockChainRotationTest extends TestCase
{
    #[Test]
    public function a_rotated_chain_verifies_when_every_segment_key_is_pinned(): void
    {
        $chain = PipelockChain::rotated();
        $verifier = new PipelockReceiptVerifier(new PipelockActionCanonicalizer);

        foreach ($chain['envelopes'] as $envelope) {
            $result = $verifier->verify($this->encode($envelope), [$chain['keys']['a'], $chain['keys']['b']]);

            $this->assertTrue($result['valid'], implode(' ', $result['errors']));
            $this->assertTrue($result['pinned']);
        }

        $result = (new PipelockChainVerifier(new PipelockActionCanonicalizer))->verify($chain['envelopes']);

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['complete']);
        $this->assertSame('genesis', $result['scope']);
        $this->assertSame(2, $result['segments']);
        $this->assertSame(3, $result['links']);
        $this->assertSame(1, $result['final_seq']);
        $this->assertSame(
            PipelockChain::hash($chain['envelopes'][4]),
            $result['head_hash'],
        );
    }

    #[Test]
    public function a_segment_signed_by_an_unpinned_key_is_rejected(): void
    {
        $chain = PipelockChain::rotated();
        $verifier = new PipelockReceiptVerifier(new PipelockActionCanonicalizer);

        $result = $verifier->verify($this->encode($chain['envelopes'][3]), [$chain['keys']['a']]);

        $this->assertFalse($result['valid']);
        $this->assertContains('The receipt was not signed by a pinned key.', $result['errors']);
    }

    #[Test]
    public function a_rotation_that_references_a_different_tail_is_rejected(): void
    {
        $chain = PipelockChain::rotated();
        $chain['envelopes'][3]['action_record']['key_transition']['prior_chain_hash'] = str_repeat('0', 64);

        $result = $this->chainVerifier()->verify($chain['envelopes']);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('does not reference the prior tail', $result['errors'][0]);
    }

    #[Test]
    public function a_rotation_that_names_the_wrong_prior_signer_is_rejected(): void
    {
        $chain = PipelockChain::rotated();
        $chain['envelopes'][3]['action_record']['key_transition']['prior_signer_key'] = str_repeat('a', 64);

        $result = $this->chainVerifier()->verify($chain['envelopes']);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('does not name the prior segment signer', $result['errors'][0]);
    }

    #[Test]
    public function a_rotation_that_does_not_change_the_signer_is_rejected(): void
    {
        $key = PipelockChain::keypair('rotation-a');
        $gt = PipelockChain::receipt($key, 0, 'genesis', 0);
        $second = PipelockChain::receipt($key, 1, PipelockChain::hash($gt), 1);
        $repeated = PipelockChain::receipt($key, 0, PipelockChain::hash($second), 2, [
            'key_transition' => [
                'prior_signer_key' => $key['public'],
                'prior_chain_seq' => 1,
                'prior_chain_hash' => PipelockChain::hash($second),
            ],
        ]);

        $result = $this->chainVerifier()->verify([$gt, $second, $repeated]);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('does not change the signer key', $result['errors'][0]);
    }

    #[Test]
    public function a_rotation_marker_mid_segment_is_rejected(): void
    {
        $chain = PipelockChain::rotated();
        $key = PipelockChain::keypair('rotation-b');
        $previous = $chain['envelopes'][3];
        $midSegment = PipelockChain::receipt($key, 1, PipelockChain::hash($previous), 9, [
            'key_transition' => [
                'prior_signer_key' => $chain['keys']['b'],
                'prior_chain_seq' => 0,
                'prior_chain_hash' => PipelockChain::hash($previous),
            ],
        ]);

        $result = $this->chainVerifier()->verify([...$chain['envelopes'], $midSegment]);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('must start at chain_seq 0', $result['errors'][0]);
    }

    #[Test]
    public function a_rotation_that_moves_the_prior_sequence_is_rejected(): void
    {
        $chain = PipelockChain::rotated();
        $chain['envelopes'][3]['action_record']['key_transition']['prior_chain_seq'] = 99;

        $result = $this->chainVerifier()->verify($chain['envelopes']);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('prior_chain_seq does not match', $result['errors'][0]);
    }

    #[Test]
    public function a_partial_slice_cannot_start_at_a_rotation(): void
    {
        $chain = PipelockChain::rotated();
        $result = $this->chainVerifier()->verify([$chain['envelopes'][3], $chain['envelopes'][4]], true);

        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('starts at a signing-key rotation', $result['errors'][0]);
    }

    private function chainVerifier(): PipelockChainVerifier
    {
        return new PipelockChainVerifier(new PipelockActionCanonicalizer);
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function encode(array $envelope): string
    {
        return (string) json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
