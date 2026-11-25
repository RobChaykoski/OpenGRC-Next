<?php

namespace App\Services\Evidence;

use App\Contracts\EvidenceSubject;
use App\Models\EvidenceLedgerEntry;
use App\Models\EvidenceState;
use App\Models\FileAttachment;
use App\Models\Policy;
use App\Models\SurveyAttachment;
use App\Models\User;
use App\Models\VendorDocument;
use App\Models\VendorUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Append-only chain of custody for evidence files.
 *
 * Each subject (audit evidence, vendor evidence, survey evidence, policy
 * document) has a hash chain. Entries are immutable; withdrawal is a recorded
 * event with a reason, never a delete. A verifier recomputes both the file hash
 * and the chain, so a database edit or a replaced file is detectable.
 */
class EvidenceLedger
{
    /** @var array<int, class-string<Model>> */
    public const SUBJECTS = [
        FileAttachment::class,
        SurveyAttachment::class,
        VendorDocument::class,
        Policy::class,
    ];

    /**
     * @param  Model&EvidenceSubject  $subject
     */
    public function submit(Model $subject, ?Model $actor = null, ?string $reason = null, array $metadata = []): EvidenceLedgerEntry
    {
        $this->ensureActiveState($subject);

        return $this->append($subject, EvidenceLedgerEntry::EVENT_SUBMITTED, $reason, $metadata, $actor);
    }

    /**
     * @param  Model&EvidenceSubject  $subject
     */
    public function withdraw(Model $subject, string $reason, ?Model $actor = null): EvidenceLedgerEntry
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('A withdrawal reason of at least 10 characters is required.');
        }

        return DB::transaction(function () use ($subject, $reason, $actor): EvidenceLedgerEntry {
            $state = $this->stateFor($subject);

            if ($state->status === EvidenceState::STATUS_WITHDRAWN) {
                throw new InvalidArgumentException('Evidence is already withdrawn.');
            }

            $entry = $this->append($subject, EvidenceLedgerEntry::EVENT_WITHDRAWN, $reason, [], $actor);

            [$actorType, $actorId] = $this->actorAttributes($actor);

            $state->update([
                'status' => EvidenceState::STATUS_WITHDRAWN,
                'reason' => $reason,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'changed_at' => now(),
            ]);

            return $entry;
        });
    }

    /**
     * @param  Model&EvidenceSubject  $old
     * @param  Model&EvidenceSubject  $replacement
     */
    public function supersede(Model $old, Model $replacement, string $reason, ?Model $actor = null): EvidenceLedgerEntry
    {
        if ($old->is($replacement)) {
            throw new InvalidArgumentException('Evidence cannot supersede itself.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('A supersede reason of at least 10 characters is required.');
        }

        return DB::transaction(function () use ($old, $replacement, $reason, $actor): EvidenceLedgerEntry {
            $entry = $this->append($old, EvidenceLedgerEntry::EVENT_SUPERSEDED, $reason, [
                'superseded_by_type' => $replacement->getMorphClass(),
                'superseded_by_id' => $replacement->getKey(),
            ], $actor);

            [$actorType, $actorId] = $this->actorAttributes($actor);

            $this->stateFor($old)->update([
                'status' => EvidenceState::STATUS_SUPERSEDED,
                'reason' => $reason,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'changed_at' => now(),
                'superseded_by_type' => $replacement->getMorphClass(),
                'superseded_by_id' => $replacement->getKey(),
            ]);

            $this->ensureActiveState($replacement);
            $this->submit($replacement, $actor, metadata: ['supersedes_type' => $old->getMorphClass(), 'supersedes_id' => $old->getKey()]);

            return $entry;
        });
    }

    /**
     * Ensure every existing evidence record has a submission entry.
     *
     * @return array{recorded: int, errors: array<int, string>}
     */
    public function backfill(): array
    {
        if (! Schema::hasTable('evidence_ledger_entries') || ! Schema::hasTable('evidence_states')) {
            return ['recorded' => 0, 'errors' => ['Evidence ledger tables are missing; run migrations.']];
        }

        $recorded = 0;
        $errors = [];

        foreach ($this->subjects() as $subject) {
            if ($subject->ledgerEntries()->exists()) {
                continue;
            }

            try {
                $actor = $this->inferActor($subject);
                $this->submit($subject, $actor, 'Backfilled before production mode was enabled', [
                    'backfilled' => true,
                    'source' => 'backfill',
                ]);
                $recorded++;
            } catch (Throwable $e) {
                $errors[] = sprintf('%s#%s: %s', class_basename($subject), $subject->getKey(), $e->getMessage());
            }
        }

        return ['recorded' => $recorded, 'errors' => $errors];
    }

    /**
     * @return array{checked: int, errors: array<int, string>, warnings: array<int, string>}
     */
    public function verifyAll(): array
    {
        if (! Schema::hasTable('evidence_ledger_entries') || ! Schema::hasTable('evidence_states')) {
            return [
                'checked' => 0,
                'errors' => ['Evidence ledger tables are missing; run migrations.'],
                'warnings' => [],
            ];
        }

        $checked = 0;
        $errors = [];
        $warnings = [];

        foreach ($this->subjects() as $subject) {
            $checked++;
            [$subjectErrors, $subjectWarnings] = $this->verifySubject($subject);
            $errors = array_merge($errors, $subjectErrors);
            $warnings = array_merge($warnings, $subjectWarnings);
        }

        $errors = array_merge($errors, $this->orphanEntries());

        return ['checked' => $checked, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    /**
     * @param  Model&EvidenceSubject  $subject
     */
    public function verifySubject(Model $subject): array
    {
        $label = class_basename($subject).'#'.$subject->getKey();
        $errors = [];
        $warnings = [];
        /** @var Collection<int, EvidenceLedgerEntry> $entries */
        $entries = $subject->ledgerEntries()->get();

        if ($entries->isEmpty()) {
            return [["{$label}: no ledger entries"], []];
        }

        $previous = 'GENESIS';

        foreach ($entries->values() as $index => $entry) {
            $expectedSequence = $index + 1;

            if ((int) $entry->sequence !== $expectedSequence) {
                $errors[] = "{$label}: ledger sequence gap at entry {$entry->id}";
            }

            if (($entry->previous_hash ?? 'GENESIS') !== $previous) {
                $errors[] = "{$label}: previous hash mismatch at entry {$entry->id}";
            }

            $recomputed = $this->entryHash($previous, $entry->only([
                'subject_type', 'subject_id', 'sequence', 'event', 'file_sha256',
                'file_path', 'reason', 'actor_type', 'actor_id', 'metadata',
            ]), $entry->created_at?->format('Y-m-d H:i:s'));

            if (! hash_equals((string) $entry->entry_hash, $recomputed)) {
                $errors[] = "{$label}: entry hash mismatch at entry {$entry->id}";
            }

            $previous = (string) $entry->entry_hash;
        }

        $state = $subject->evidenceState()->first();
        if (! $state instanceof EvidenceState) {
            $errors[] = "{$label}: missing evidence state";
        } else {
            if ($state->status === EvidenceState::STATUS_WITHDRAWN && blank($state->reason)) {
                $errors[] = "{$label}: withdrawn without a reason";
            }

            if ($state->status === EvidenceState::STATUS_SUPERSEDED && ! $state->superseded_by_id) {
                $errors[] = "{$label}: superseded without a replacement";
            }
        }

        $last = $entries->last();
        $currentHash = $this->fileHash($subject);

        if ($last?->file_sha256 !== null) {
            if ($currentHash === null) {
                $errors[] = "{$label}: evidence file is missing";
            } elseif (! hash_equals((string) $last->file_sha256, $currentHash)) {
                $errors[] = "{$label}: evidence file hash does not match the ledger";
            }
        } elseif ($currentHash !== null) {
            $warnings[] = "{$label}: ledger entry has no file hash";
        }

        if (method_exists($subject, 'trashed') && $subject->trashed()) {
            $errors[] = "{$label}: evidence record is soft-deleted";
        }

        if ($subject instanceof FileAttachment) {
            if (! $subject->audit_id && ! $subject->data_request_id && ! $subject->data_request_response_id) {
                $errors[] = "{$label}: evidence is not referenced by an audit or data request";
            }
        }

        return [$errors, $warnings];
    }

    /**
     * @return array<int, string>
     */
    protected function orphanEntries(): array
    {
        $errors = [];

        $subjects = DB::table('evidence_ledger_entries')
            ->select('subject_type', 'subject_id')
            ->distinct()
            ->get();

        foreach ($subjects as $row) {
            if (! class_exists($row->subject_type)) {
                $errors[] = "Ledger references an unknown subject type {$row->subject_type}";

                continue;
            }

            $query = $row->subject_type::query();

            // Only soft-deleting subjects can still be found after a hide. A
            // direct `withTrashed()` call on the others is a BadMethodCallException
            // and would make `verifyAll()` unusable on a normal install.
            if (method_exists($row->subject_type, 'bootSoftDeletes')) {
                $query->withTrashed();
            }

            $model = $query->find($row->subject_id);

            if (! $model) {
                $errors[] = "Ledger references missing evidence {$row->subject_type}#{$row->subject_id}";
            }
        }

        return $errors;
    }

    /**
     * @param  Model&EvidenceSubject  $subject
     */
    public function fileHash(Model $subject): ?string
    {
        $path = $subject->evidencePath();

        if ($path === null) {
            return null;
        }

        try {
            $disk = Storage::disk($subject->evidenceDisk());

            if (! $disk->exists($path)) {
                return null;
            }

            return hash('sha256', $disk->get($path));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, Model&EvidenceSubject>
     */
    protected function subjects()
    {
        $subjects = collect();

        foreach (self::SUBJECTS as $class) {
            $query = $class::query();
            $column = (new $class)->evidencePathColumn();
            $query->whereNotNull($column)->where($column, '!=', '');

            if (method_exists($class, 'bootSoftDeletes')) {
                $query->withTrashed();
            }

            $subjects = $subjects->merge($query->get());
        }

        return $subjects;
    }

    /**
     * @param  Model&EvidenceSubject  $subject
     */
    protected function ensureActiveState(Model $subject): EvidenceState
    {
        return EvidenceState::firstOrCreate(
            ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()],
            ['status' => EvidenceState::STATUS_ACTIVE]
        );
    }

    /**
     * @param  Model&EvidenceSubject  $subject
     */
    protected function stateFor(Model $subject): EvidenceState
    {
        return EvidenceState::firstOrCreate(
            ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()],
            ['status' => EvidenceState::STATUS_ACTIVE]
        );
    }

    /**
     * @param  Model&EvidenceSubject  $subject
     */
    protected function append(Model $subject, string $event, ?string $reason, array $metadata, ?Model $actor): EvidenceLedgerEntry
    {
        return DB::transaction(function () use ($subject, $event, $reason, $metadata, $actor): EvidenceLedgerEntry {
            $model = $subject->newQuery()
                ->whereKey($subject->getKey())
                ->firstOrFail();

            $last = EvidenceLedgerEntry::query()
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            $sequence = ($last?->sequence ?? 0) + 1;
            $previousHash = $last?->entry_hash;
            $createdAt = now()->startOfSecond();
            [$actorType, $actorId] = $this->actorAttributes($actor);

            $metadata = array_merge($metadata, [
                'app_version' => (string) config('app.version', 'dev'),
                'environment' => app()->environment(),
            ]);

            $payload = [
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => (int) $subject->getKey(),
                'sequence' => $sequence,
                'event' => $event,
                'file_sha256' => $this->fileHash($subject),
                'file_path' => $subject->evidencePath(),
                'reason' => $reason,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'metadata' => $this->canonicalize($metadata),
            ];

            $entryHash = $this->entryHash($previousHash, $payload, $createdAt->format('Y-m-d H:i:s'));

            if ($payload['file_sha256'] !== null && Schema::hasColumn($subject->getTable(), 'file_sha256')) {
                DB::table($subject->getTable())
                    ->where($subject->getKeyName(), $subject->getKey())
                    ->update(['file_sha256' => $payload['file_sha256']]);
            }

            return EvidenceLedgerEntry::create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'sequence' => $sequence,
                'event' => $event,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'reason' => $reason,
                'file_sha256' => $payload['file_sha256'],
                'file_path' => $payload['file_path'],
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
                'metadata' => $metadata,
                'created_at' => $createdAt,
            ]);
        });
    }

    protected function entryHash(?string $previousHash, array $payload, ?string $createdAt): string
    {
        $canonical = $this->canonicalize($payload + ['created_at' => $createdAt]);

        return hash('sha256', ($previousHash ?? 'GENESIS').'|'.json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    protected function actorAttributes(?Model $actor = null): array
    {
        $actor ??= auth()->user() ?? auth('vendor')->user();

        if (! $actor instanceof Model || ! $actor->exists) {
            return [null, null];
        }

        return [$actor->getMorphClass(), $actor->getKey()];
    }

    /**
     * @param  Model&EvidenceSubject  $subject
     */
    protected function inferActor(Model $subject): ?Model
    {
        foreach (['uploadedBy', 'createdBy'] as $relation) {
            if (! method_exists($subject, $relation)) {
                continue;
            }

            $actor = $subject->{$relation}()->first();

            if ($actor instanceof Model) {
                return $actor;
            }
        }

        foreach (['uploaded_by', 'created_by_id'] as $column) {
            $id = $subject->getAttribute($column);

            if ($id) {
                $actor = User::find($id) ?? VendorUser::find($id);

                if ($actor instanceof Model) {
                    return $actor;
                }
            }
        }

        return null;
    }

    /**
     * Recursively sort array keys so the hash is stable across JSON round-trips.
     */
    protected function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonicalize($item), $value);
    }
}
