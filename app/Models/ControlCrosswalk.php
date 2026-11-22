<?php

namespace App\Models;

use App\Enums\CrosswalkConfidence;
use App\Enums\CrosswalkStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A reviewed mapping between two imported controls.
 *
 * A crosswalk says "these two requirements serve the same programme intent".
 * It does not say the text is equivalent and it is not compliance evidence.
 */
class ControlCrosswalk extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'source_control_id',
        'target_control_id',
        'rationale',
        'confidence',
        'status',
        'review_note',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence' => CrosswalkConfidence::class,
        'status' => CrosswalkStatus::class,
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Control, $this>
     */
    public function sourceControl(): BelongsTo
    {
        return $this->belongsTo(Control::class, 'source_control_id');
    }

    /**
     * @return BelongsTo<Control, $this>
     */
    public function targetControl(): BelongsTo
    {
        return $this->belongsTo(Control::class, 'target_control_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reference(): string
    {
        return 'XW-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Builder<ControlCrosswalk>  $query
     * @return Builder<ControlCrosswalk>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', CrosswalkStatus::Approved->value);
    }

    /**
     * @param  Builder<ControlCrosswalk>  $query
     * @return Builder<ControlCrosswalk>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', CrosswalkStatus::Draft->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'confidence', 'reviewed_by', 'reviewed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
