<?php

namespace App\Models\Concerns;

use App\Contracts\EvidenceSubject;
use App\Exceptions\EvidenceImmutableException;
use App\Models\EvidenceLedgerEntry;
use App\Models\EvidenceState;
use App\Services\Evidence\EvidenceLedger;
use App\Support\InstanceMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Adds an append-only evidence ledger to a model that stores a file.
 *
 * In production the underlying record cannot be deleted and its file path
 * cannot be swapped. Withdrawal is a ledger event with a reason, not a delete.
 */
trait HasEvidenceLedger
{
    public static function bootHasEvidenceLedger(): void
    {
        static::created(function (Model $model): void {
            if (! $model instanceof EvidenceSubject) {
                return;
            }

            if ($model->evidencePath() === null) {
                return;
            }

            app(EvidenceLedger::class)->submit(
                $model,
                metadata: ['source' => 'created', 'provisional' => ! InstanceMode::isProduction()]
            );
        });

        static::updating(function (Model $model): void {
            if (! InstanceMode::isProduction() || ! $model instanceof EvidenceSubject) {
                return;
            }

            if ($model->isDirty($model->evidencePathColumn())) {
                throw EvidenceImmutableException::onPathChange($model);
            }
        });

        static::deleting(function (Model $model): void {
            if (! InstanceMode::isProduction() || ! $model instanceof EvidenceSubject) {
                return;
            }

            throw EvidenceImmutableException::onDelete($model);
        });
    }

    public function evidencePathColumn(): string
    {
        return 'file_path';
    }

    public function evidencePath(): ?string
    {
        $path = $this->getAttribute($this->evidencePathColumn());

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function evidenceDisk(): string
    {
        return setting('storage.driver', 'private');
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(EvidenceLedgerEntry::class, 'subject')
            ->orderBy('sequence');
    }

    public function evidenceState(): MorphOne
    {
        return $this->morphOne(EvidenceState::class, 'subject');
    }
}
