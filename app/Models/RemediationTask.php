<?php

namespace App\Models;

use App\Enums\RemediationTaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The work that closes a finding, risk or control gap.
 *
 * A task has an owner and a date. It is deliberately lighter than a project
 * plan: no dependencies, milestones or ticket sync.
 */
class RemediationTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'finding_id',
        'risk_id',
        'control_id',
        'title',
        'description',
        'owner_id',
        'due_at',
        'status',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'due_at' => 'date',
        'status' => RemediationTaskStatus::class,
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (RemediationTask $task): void {
            if ($task->status === RemediationTaskStatus::Done && $task->completed_at === null) {
                $task->completed_at = now();
            }

            if ($task->status !== RemediationTaskStatus::Done) {
                $task->completed_at = null;
            }
        });
    }

    /**
     * @return BelongsTo<Finding, $this>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * @return BelongsTo<Risk, $this>
     */
    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    /**
     * @return BelongsTo<Control, $this>
     */
    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reference(): string
    {
        return 'REM-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_at !== null
            && $this->due_at->lt(today());
    }

    /**
     * @param  Builder<RemediationTask>  $query
     * @return Builder<RemediationTask>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RemediationTaskStatus::Open->value,
            RemediationTaskStatus::InProgress->value,
            RemediationTaskStatus::Blocked->value,
        ]);
    }

    /**
     * @param  Builder<RemediationTask>  $query
     * @return Builder<RemediationTask>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today());
    }
}
