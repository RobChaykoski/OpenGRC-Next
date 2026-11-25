<?php

namespace Tests\Unit\Evidence;

use App\Services\Evidence\Pipelock\PipelockActionCanonicalizer;
use App\Services\Evidence\Pipelock\PipelockReceiptVerifier;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PipelockReceiptVerifierTest extends TestCase
{
    #[Test]
    public function the_published_fixture_verifies_with_a_pinned_signer_key(): void
    {
        $envelope = $this->envelope();
        $result = (new PipelockReceiptVerifier(new PipelockActionCanonicalizer))
            ->verify($this->fixtureJson(), $envelope['signer_key']);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['signature']);
        $this->assertTrue($result['pinned']);
        $this->assertSame($envelope['signer_key'], $result['signer_key']);
        $this->assertNotNull($result['canonical_sha256']);
        $this->assertSame([], $result['errors']);
    }

    #[Test]
    public function an_unpinned_receipt_verifies_but_warns(): void
    {
        $result = (new PipelockReceiptVerifier(new PipelockActionCanonicalizer))->verify($this->fixtureJson());

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['pinned']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('inline signer_key', $result['warnings'][0]);
    }

    #[Test]
    public function a_changed_verdict_breaks_the_signature(): void
    {
        $envelope = $this->envelope();
        $envelope['action_record']['verdict'] = 'block';
        $result = (new PipelockReceiptVerifier(new PipelockActionCanonicalizer))
            ->verify($this->encode($envelope), $envelope['signer_key']);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['signature']);
        $this->assertContains('The Ed25519 signature is invalid.', $result['errors']);
    }

    #[Test]
    public function a_changed_signature_is_rejected(): void
    {
        $envelope = $this->envelope();
        $envelope['signature'] = 'ed25519:'.str_repeat('0', 128);
        $result = (new PipelockReceiptVerifier(new PipelockActionCanonicalizer))
            ->verify($this->encode($envelope), $envelope['signer_key']);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['signature']);
    }

    #[Test]
    public function a_foreign_pinned_key_is_rejected(): void
    {
        $result = (new PipelockReceiptVerifier(new PipelockActionCanonicalizer))
            ->verify($this->fixtureJson(), str_repeat('a', 64));

        $this->assertFalse($result['valid']);
        $this->assertContains('The receipt was not signed by the pinned key.', $result['errors']);
    }

    #[Test]
    public function an_unknown_action_field_is_rejected(): void
    {
        $envelope = $this->envelope();
        $envelope['action_record']['invented_field'] = 'x';
        $result = (new PipelockReceiptVerifier(new PipelockActionCanonicalizer))
            ->verify($this->encode($envelope), $envelope['signer_key']);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Unknown Pipelock action fields', $result['errors'][0]);
    }

    #[Test]
    public function an_unknown_envelope_version_is_rejected(): void
    {
        $envelope = $this->envelope();
        $envelope['version'] = 2;
        $result = (new PipelockReceiptVerifier(new PipelockActionCanonicalizer))
            ->verify($this->encode($envelope), $envelope['signer_key']);

        $this->assertFalse($result['valid']);
        $this->assertContains('The receipt version is not 1.', $result['errors']);
    }

    #[Test]
    public function an_unknown_action_type_is_rejected(): void
    {
        $envelope = $this->envelope();
        $envelope['action_record']['action_type'] = 'teleport';
        $result = (new PipelockReceiptVerifier(new PipelockActionCanonicalizer))
            ->verify($this->encode($envelope), $envelope['signer_key']);

        $this->assertFalse($result['valid']);
        $this->assertContains('action_type is not recognised.', $result['errors']);
    }

    #[Test]
    public function canonical_json_uses_go_ordering_omitempty_and_html_escaping(): void
    {
        $action = [
            'version' => 1,
            'action_id' => 'canonical-test',
            'action_type' => 'read',
            'timestamp' => '2026-01-01T00:00:00.123456789Z',
            'principal' => 'org:test',
            'actor' => 'agent:test',
            'delegation_chain' => null,
            'target' => 'https://example.test/?a=<b>&c',
            'side_effect_class' => 'none',
            'reversibility' => 'full',
            'policy_hash' => '',
            'verdict' => 'allow',
            'transport' => 'https',
            'chain_prev_hash' => 'genesis',
            'chain_seq' => 0,
        ];

        $canonical = (new PipelockActionCanonicalizer)->canonical($action);

        $this->assertStringContainsString('"timestamp":"2026-01-01T00:00:00.123456789Z"', $canonical);
        $this->assertStringContainsString('"delegation_chain":null', $canonical);
        $this->assertStringContainsString('\\u003c', $canonical);
        $this->assertStringContainsString('\\u003e', $canonical);
        $this->assertStringContainsString('\\u0026', $canonical);
        $this->assertStringNotContainsString('"intent"', $canonical);
    }

    #[Test]
    public function unknown_fields_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PipelockActionCanonicalizer)->canonical([
            'version' => 1,
            'action_id' => 'x',
            'action_type' => 'read',
            'timestamp' => '2026-01-01T00:00:00Z',
            'target' => 'https://example.test',
            'verdict' => 'allow',
            'transport' => 'https',
            'chain_prev_hash' => 'genesis',
            'chain_seq' => 0,
            'unexpected' => true,
        ]);
    }

    #[Test]
    public function canonical_json_supports_nested_redaction_and_shield_summaries(): void
    {
        $action = [
            'version' => 1,
            'action_id' => 'canonical-nested',
            'action_type' => 'write',
            'timestamp' => '2026-01-01T00:00:00Z',
            'principal' => 'org:test',
            'actor' => 'agent:test',
            'delegation_chain' => [],
            'target' => 'https://example.test',
            'side_effect_class' => 'external_write',
            'reversibility' => 'full',
            'policy_hash' => '',
            'verdict' => 'strip',
            'transport' => 'https',
            'redaction' => [
                'profile' => 'default',
                'total_redactions' => 2,
                'by_class' => ['b' => 1, 'a' => 2],
            ],
            'shield' => [
                'pipeline' => 'browser',
                'total_rewrites' => 3,
                'partial' => true,
            ],
            'chain_prev_hash' => 'genesis',
            'chain_seq' => 0,
        ];

        $canonical = (new PipelockActionCanonicalizer)->canonical($action);

        $this->assertStringContainsString('"delegation_chain":[]', $canonical);
        $this->assertStringContainsString('"redaction":{"profile":"default","total_redactions":2,"by_class":{"a":2,"b":1}}', $canonical);
        $this->assertStringContainsString('"shield":{"pipeline":"browser","total_rewrites":3,"partial":true}', $canonical);
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(): array
    {
        try {
            $decoded = json_decode($this->fixtureJson(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->fail('The Pipelock fixture is not valid JSON: '.$exception->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function fixtureJson(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/pipelock/valid-single.json'));
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function encode(array $envelope): string
    {
        return (string) json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
