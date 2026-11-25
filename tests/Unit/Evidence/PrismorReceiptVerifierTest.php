<?php

namespace Tests\Unit\Evidence;

use App\Services\Evidence\Prismor\PrismorReceiptVerifier;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrismorReceiptVerifierTest extends TestCase
{
    #[Test]
    public function the_published_example_verifies_with_a_pinned_key(): void
    {
        $receipt = $this->receipt();
        $result = (new PrismorReceiptVerifier)->verify($receipt, $receipt['signing_pubkey']);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['integrity']);
        $this->assertTrue($result['signature']);
        $this->assertTrue($result['pinned']);
        $this->assertSame($receipt['signing_key_id'], $result['key_id']);
        $this->assertSame($receipt['hash'], $result['chain_hash']);
        $this->assertSame([], $result['errors']);
    }

    #[Test]
    public function an_unpinned_receipt_verifies_but_warns(): void
    {
        $result = (new PrismorReceiptVerifier)->verify($this->receipt());

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['pinned']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('inline key', $result['warnings'][0]);
    }

    #[Test]
    public function a_changed_verdict_breaks_the_chain_hash_but_not_the_signature(): void
    {
        $receipt = $this->receipt();
        $receipt['verdict'] = 'allowed';

        $result = (new PrismorReceiptVerifier)->verify($receipt, $receipt['signing_pubkey']);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['integrity']);
        $this->assertTrue($result['signature']);
        $this->assertContains('The chain hash does not match the receipt fields.', $result['errors']);
    }

    #[Test]
    public function a_changed_device_breaks_the_signature_but_not_the_chain_hash(): void
    {
        $receipt = $this->receipt();
        $receipt['device_id'] = 'dev-evil';

        $result = (new PrismorReceiptVerifier)->verify($receipt, $receipt['signing_pubkey']);

        $this->assertFalse($result['valid']);
        $this->assertTrue($result['integrity']);
        $this->assertFalse($result['signature']);
    }

    #[Test]
    public function a_foreign_pinned_key_is_rejected(): void
    {
        $receipt = $this->receipt();
        $pair = sodium_crypto_sign_keypair();

        $result = (new PrismorReceiptVerifier)->verify($receipt, base64_encode(sodium_crypto_sign_publickey($pair)));

        $this->assertFalse($result['valid']);
        $this->assertContains('The receipt signing key does not match the pinned key.', $result['errors']);
    }

    #[Test]
    public function a_tampered_signature_is_rejected(): void
    {
        $receipt = $this->receipt();
        $receipt['signature'] = base64_encode(str_repeat("\0", 64));

        $result = (new PrismorReceiptVerifier)->verify($receipt, $receipt['signing_pubkey']);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['signature']);
    }

    #[Test]
    public function a_malformed_receipt_is_rejected(): void
    {
        $receipt = $this->receipt();
        unset($receipt['event_id']);

        $result = (new PrismorReceiptVerifier)->verify($receipt, $receipt['signing_pubkey']);

        $this->assertFalse($result['valid']);
        $this->assertNull($result['receipt']);
        $this->assertContains('event_id is required.', $result['errors']);
    }

    #[Test]
    public function an_unsupported_algorithm_is_rejected(): void
    {
        $receipt = $this->receipt();
        $receipt['signing_alg'] = 'rsa';

        $result = (new PrismorReceiptVerifier)->verify($receipt, $receipt['signing_pubkey']);

        $this->assertFalse($result['valid']);
        $this->assertContains('signing_alg must be ed25519.', $result['errors']);
    }

    /**
     * @return array<string, mixed>
     */
    private function receipt(): array
    {
        $path = base_path('tests/Fixtures/prismor/receipt.json');

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->fail('The Prismor fixture is not valid JSON: '.$exception->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }
}
