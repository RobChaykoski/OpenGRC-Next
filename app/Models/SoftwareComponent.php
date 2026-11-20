<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One package coordinate from an SBOM.
 *
 * `component_key` is the purl when present, otherwise name@version. It is unique
 * within a document so a re-import updates the component instead of duplicating it.
 */
class SoftwareComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'sbom_document_id',
        'component_key',
        'purl',
        'name',
        'version',
        'supplier',
        'license',
        'ecosystem',
        'scope',
        'type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<SbomDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SbomDocument::class, 'sbom_document_id');
    }

    /**
     * @return HasMany<SoftwareVulnerability, $this>
     */
    public function vulnerabilities(): HasMany
    {
        return $this->hasMany(SoftwareVulnerability::class);
    }

    public function displayName(): string
    {
        return $this->version === null ? $this->name : $this->name.'@'.$this->version;
    }
}
