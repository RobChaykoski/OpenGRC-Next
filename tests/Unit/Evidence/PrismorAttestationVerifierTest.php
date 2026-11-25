<?php

namespace Tests\Unit\Evidence;

use App\Services\Evidence\Prismor\PrismorAttestationVerifier;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class PrismorAttestationVerifierTest extends TestCase
{
    #[Test]
    public function the_signed_fixture_verifies_with_a_pinned_key(): void
    {
        $bundle = $this->bundle();
        $result = (new PrismorAttestationVerifier)->verify($this->fixtureJson(), (string) $bundle['signing_pubkey']);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['content_hash']);
        $this->assertTrue($result['signature']);
        $this->assertTrue($result['pinned']);
        $this->assertSame($bundle['signing_key_id'], $result['key_id']);
        $this->assertSame([], $result['errors']);
    }

    #[Test]
    public function an_unpinned_bundle_verifies_but_warns(): void
    {
        $result = (new PrismorAttestationVerifier)->verify($this->fixtureJson());

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['pinned']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('inline key', $result['warnings'][0]);
    }

    #[Test]
    public function a_body_edit_breaks_the_content_hash_but_not_the_signature(): void
    {
        $bundle = $this->bundle();
        $bundle['audit_findings'][0]['message'] = 'changed';
        $result = (new PrismorAttestationVerifier)->verify(
            $this->encode($bundle),
            (string) $bundle['signing_pubkey'],
        );

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['content_hash']);
        $this->assertTrue($result['signature']);
    }

    #[Test]
    public function a_device_edit_breaks_the_signature_as_well_as_the_content_hash(): void
    {
        $bundle = $this->bundle();
        $bundle['device_id'] = 'someone-elses-device';
        $result = (new PrismorAttestationVerifier)->verify(
            $this->encode($bundle),
            (string) $bundle['signing_pubkey'],
        );

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['content_hash']);
        $this->assertFalse($result['signature']);
    }

    #[Test]
    public function a_foreign_pinned_key_is_rejected(): void
    {
        $pair = sodium_crypto_sign_keypair();
        $result = (new PrismorAttestationVerifier)->verify(
            $this->fixtureJson(),
            base64_encode(sodium_crypto_sign_publickey($pair)),
        );

        $this->assertFalse($result['valid']);
        $this->assertContains('The signature was not produced by the pinned key.', $result['errors']);
    }

    #[Test]
    public function an_unsigned_bundle_is_not_valid(): void
    {
        $bundle = $this->bundle();

        foreach (['signature', 'signing_alg', 'signing_pubkey', 'signing_key_id'] as $field) {
            unset($bundle[$field]);
        }

        $result = (new PrismorAttestationVerifier)->verify($this->encode($bundle));

        $this->assertFalse($result['valid']);
        $this->assertTrue($result['content_hash']);
        $this->assertFalse($result['signature']);
        $this->assertContains('The bundle is unsigned.', $result['warnings']);
    }

    #[Test]
    public function a_wrong_schema_is_rejected(): void
    {
        $bundle = $this->bundle();
        $bundle['schema'] = 'prismor.attestation.v2';

        $result = (new PrismorAttestationVerifier)->verify($this->encode($bundle));

        $this->assertFalse($result['valid']);
        $this->assertContains('The schema is not prismor.attestation.v1.', $result['errors']);
    }

    #[Test]
    public function canonical_json_sorts_keys_and_keeps_objects_and_arrays_distinct(): void
    {
        $this->assertSame('{"a":2,"b":1}', PrismorAttestationVerifier::canonical((object) ['b' => 1, 'a' => 2]));
        $this->assertSame('{"a":{},"b":[]}', PrismorAttestationVerifier::canonical((object) ['b' => [], 'a' => new stdClass]));
        $this->assertSame('[3,{"y":2,"z":1}]', PrismorAttestationVerifier::canonical([3, (object) ['z' => 1, 'y' => 2]]));
    }

    /**
     * @return array<string, mixed>
     */
    private function bundle(): array
    {
        try {
            $decoded = json_decode($this->fixtureJson(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->fail('The attestation fixture is not valid JSON: '.$exception->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function fixtureJson(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/prismor/attestation.json'));
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    private function encode(array $bundle): string
    {
        return (string) json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
