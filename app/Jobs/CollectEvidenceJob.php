<?php

namespace App\Jobs;

use App\Models\EvidenceSource;
use App\Services\Evidence\CollectionRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Run one source in the worker so the admin UI does not block on a vendor API.
 * The unique lock prevents two clicks from collecting the same source at once.
 */
class CollectEvidenceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $uniqueFor = 300;

    public function __construct(protected int $sourceId) {}

    public function uniqueId(): string
    {
        return 'evidence-source:'.$this->sourceId;
    }

    public function handle(CollectionRunner $runner): void
    {
        $source = EvidenceSource::query()->find($this->sourceId);

        if (! $source instanceof EvidenceSource || ! $source->isEnabled()) {
            return;
        }

        $runner->run($source);
    }
}
