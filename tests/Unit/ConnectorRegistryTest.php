<?php

namespace Tests\Unit;

use App\Contracts\EvidenceConnector;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use App\Services\Evidence\ConnectorRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ConnectorRegistryTest extends TestCase
{
    #[Test]
    public function it_resolves_a_registered_connector(): void
    {
        $registry = new ConnectorRegistry;
        $registry->register(EvidenceSourceType::Patchmon, FakeEvidenceConnector::class);

        $connector = $registry->for(new EvidenceSource(['type' => EvidenceSourceType::Patchmon]));

        $this->assertInstanceOf(FakeEvidenceConnector::class, $connector);
    }

    #[Test]
    public function an_unregistered_type_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);

        (new ConnectorRegistry)->for(new EvidenceSource(['type' => EvidenceSourceType::Wazuh]));
    }

    #[Test]
    public function it_rejects_a_class_that_is_not_a_connector(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConnectorRegistry)->register(EvidenceSourceType::Other, \stdClass::class);
    }

    #[Test]
    public function it_reports_which_types_are_registered(): void
    {
        $registry = new ConnectorRegistry;
        $registry->register(EvidenceSourceType::Nessus, FakeEvidenceConnector::class);

        $this->assertTrue($registry->has(EvidenceSourceType::Nessus));
        $this->assertFalse($registry->has(EvidenceSourceType::Wazuh));
        $this->assertArrayHasKey('nessus', $registry->all());
    }
}

final class FakeEvidenceConnector implements EvidenceConnector
{
    public function type(): EvidenceSourceType
    {
        return EvidenceSourceType::Patchmon;
    }

    public function collect(EvidenceSource $source): iterable
    {
        return [];
    }
}
