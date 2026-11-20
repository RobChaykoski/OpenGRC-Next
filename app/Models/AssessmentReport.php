<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An imported scanner report.
 *
 * The file stays in the CI or document store; the report hash and the derived
 * findings are the evidence kept here.
 */
class AssessmentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'format',
        'tool',
        'tool_version',
        'report_sha256',
        'application_id',
        'asset_id',
        'finding_count',
        'metadata',
        'imported_at',
    ];

    protected $casts = [
        'finding_count' => 'integer',
        'metadata' => 'array',
        'imported_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function reference(): string
    {
        return 'ASMT-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }
}
