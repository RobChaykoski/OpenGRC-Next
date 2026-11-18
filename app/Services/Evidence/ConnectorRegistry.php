<?php

namespace App\Services\Evidence;

use App\Contracts\EvidenceConnector;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use InvalidArgumentException;
use RuntimeException;

/**
 * Resolve an evidence source to the connector that understands it.
 *
 * A source type without a registered connector is an operator error, not a
 * silent no-op: collection must fail visibly so the control stays Unknown.
 */
class ConnectorRegistry
{
    /** @var array<string, class-string<EvidenceConnector>> */
    protected array $connectors = [];

    /**
     * @param  class-string<EvidenceConnector>  $connector
     */
    public function register(EvidenceSourceType|string $type, string $connector): void
    {
        if (! is_a($connector, EvidenceConnector::class, true)) {
            throw new InvalidArgumentException(
                "{$connector} must implement ".EvidenceConnector::class.'.'
            );
        }

        $this->connectors[$this->key($type)] = $connector;
    }

    public function has(EvidenceSourceType|string $type): bool
    {
        return array_key_exists($this->key($type), $this->connectors);
    }

    public function for(EvidenceSource $source): EvidenceConnector
    {
        $type = $source->type;
        $key = $type instanceof EvidenceSourceType ? $type->value : (string) $type;
        $connector = $this->connectors[$key] ?? null;

        if ($connector === null) {
            throw new RuntimeException(
                "No connector is registered for evidence source type [{$key}]."
            );
        }

        return app($connector);
    }

    /**
     * @return array<string, class-string<EvidenceConnector>>
     */
    public function all(): array
    {
        return $this->connectors;
    }

    protected function key(EvidenceSourceType|string $type): string
    {
        return $type instanceof EvidenceSourceType ? $type->value : $type;
    }
}
