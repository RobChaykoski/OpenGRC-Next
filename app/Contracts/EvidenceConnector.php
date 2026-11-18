<?php

namespace App\Contracts;

use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use App\Support\Evidence\NormalisedEvidence;

/**
 * A vendor adapter that reads a source and normalises its measurements.
 *
 * Connectors own vendor authentication and payload parsing. They never write
 * evidence themselves: the ingestor hashes, deduplicates, and stores it.
 */
interface EvidenceConnector
{
    public function type(): EvidenceSourceType;

    /**
     * Collect a bounded batch of measurements from the source.
     *
     * Implementations must only issue read requests. A missing measurement is
     * returned as Unknown, never omitted silently.
     *
     * @return iterable<int, NormalisedEvidence>
     */
    public function collect(EvidenceSource $source): iterable;
}
