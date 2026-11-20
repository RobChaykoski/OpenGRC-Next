<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A point-in-time software bill of materials.
 *
 * The document and its hash are the evidence. Components and advisories are
 * derived records; a re-import with the same hash is treated as the same snapshot.
 */
class SbomDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'format',
        'spec_version',
        'document_sha256',
        'source',
        'component_count',
        'application_id',
        'asset_id',
        'imported_by_id',
        'metadata',
        'imported_at',
    ];

    protected $casts = [
        'component_count' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_id');
    }

    /**
     * @return HasMany<SoftwareComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(SoftwareComponent::class);
    }

    public function reference(): string
    {
        return 'SBOM-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }
}
