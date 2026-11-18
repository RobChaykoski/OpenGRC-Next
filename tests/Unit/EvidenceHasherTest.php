<?php

namespace Tests\Unit;

use App\Models\EvidenceRecord;
use App\Services\Evidence\EvidenceHasher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceHasherTest extends TestCase
{
    #[Test]
    public function key_order_does_not_change_the_hash(): void
    {
        $hasher = new EvidenceHasher;

        $this->assertSame(
            $hasher->hash(['b' => 2, 'a' => ['d' => 4, 'c' => 3]]),
            $hasher->hash(['a' => ['c' => 3, 'd' => 4], 'b' => 2]),
        );
    }

    #[Test]
    public function it_detects_a_payload_that_no_longer_matches_its_digest(): void
    {
        $hasher = new EvidenceHasher;
        $payload = ['securityUpdates' => 0];

        $record = new EvidenceRecord([
            'payload' => $payload,
            'payload_sha256' => $hasher->hash($payload),
        ]);

        $this->assertTrue($hasher->matches($record));

        $record->setAttribute('payload', ['securityUpdates' => 5]);

        $this->assertFalse($hasher->matches($record));
    }

    #[Test]
    public function pretty_json_is_canonical_and_readable(): void
    {
        $json = (new EvidenceHasher)->prettyJson(['b' => 2, 'a' => 1]);

        $this->assertStringContainsString('"a": 1', $json);
        $this->assertStringContainsString('"b": 2', $json);
        $this->assertStringContainsString("\n", $json);
    }
}
