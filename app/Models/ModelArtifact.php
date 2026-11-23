<?php

namespace App\Models;

use App\Enums\ModelArtifactFormat;
use App\Enums\ModelArtifactStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A registered AI model artifact (ADR 0059).
 *
 * The register stores identity, format, provenance, per-file hashes and the
 * approval decision. It never stores tensor data, tokenizer vocabulary or a
 * prompt template body; those stay in the registry or artifact store.
 */
class ModelArtifact extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'source',
        'revision',
        'format',
        'license',
        'architecture',
        'quantization',
        'context_length',
        'tensor_count',
        'file_count',
        'total_bytes',
        'content_hash',
        'tokenizer_hash',
        'chat_template_hash',
        'conversion_tool',
        'conversion_version',
        'conversion_recorded_at',
        'provenance_status',
        'provenance_verified_at',
        'status',
        'owner_id',
        'asset_id',
        'application_id',
        'approved_by',
        'approved_at',
        'scan_tool',
        'scan_tool_version',
        'last_scanned_at',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'format' => ModelArtifactFormat::class,
        'status' => ModelArtifactStatus::class,
        'context_length' => 'integer',
        'tensor_count' => 'integer',
        'file_count' => 'integer',
        'total_bytes' => 'integer',
        'conversion_recorded_at' => 'datetime',
        'provenance_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return HasMany<ModelArtifactFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ModelArtifactFile::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPickle(): bool
    {
        return $this->format instanceof ModelArtifactFormat
            && $this->format->executesOnLoad();
    }

    /**
     * Provenance is known only when the source revision and a content hash are
     * both recorded. A self-declared name is not provenance.
     */
    public function hasKnownProvenance(): bool
    {
        return filled($this->source)
            && filled($this->revision)
            && filled($this->content_hash);
    }

    public function isApproved(): bool
    {
        return $this->status === ModelArtifactStatus::Approved
            && $this->approved_at !== null;
    }

    /**
     * @param  Builder<ModelArtifact>  $query
     * @return Builder<ModelArtifact>
     */
    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('status', ModelArtifactStatus::Proposed->value)
                ->orWhereNull('provenance_verified_at')
                ->orWhereNull('content_hash');
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'source',
                'revision',
                'format',
                'license',
                'architecture',
                'quantization',
                'content_hash',
                'status',
                'owner_id',
                'asset_id',
                'application_id',
                'approved_by',
                'approved_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
