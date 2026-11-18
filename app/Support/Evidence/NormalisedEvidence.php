<?php

namespace App\Support\Evidence;

use App\Enums\ControlStatus;
use Carbon\CarbonImmutable;

/**
 * One vendor-neutral measurement produced by a connector (ADR 0009).
 *
 * The connector names the asset and control by their OpenGRC identifiers. The
 * ingestor resolves them to rows; an unresolved reference stays null rather
 * than guessing.
 */
final readonly class NormalisedEvidence
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $externalId,
        public string $evidenceType,
        public ControlStatus $status,
        public CarbonImmutable $collectedAt,
        public array $payload = [],
        public ?string $assetIdentifier = null,
        public ?string $controlIdentifier = null,
    ) {}
}
