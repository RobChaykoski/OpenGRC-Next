<?php

namespace App\Models;

use App\Enums\EvidenceSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A deterministic rule that turns a measurement into a control state.
 *
 * Same payload and same rule version must always produce the same result. A
 * change to the rule is a new version, never an edit of history.
 */
class ControlValidationRule extends Model
{
    use LogsActivity;

    protected $fillable = [
        'control_id',
        'source_id',
        'definition',
        'severity',
        'enabled',
        'version',
    ];

    protected $casts = [
        'definition' => 'array',
        'severity' => EvidenceSeverity::class,
        'enabled' => 'boolean',
        'version' => 'integer',
    ];

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class, 'control_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EvidenceSource::class, 'source_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(ControlValidation::class, 'rule_id');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['enabled', 'severity', 'version', 'control_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
