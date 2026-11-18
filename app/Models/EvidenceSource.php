<?php

namespace App\Models;

use App\Enums\EvidenceSourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A read-only source of machine evidence (ADR 0009).
 *
 * Credentials live in the encrypted `configuration` attribute. They must never
 * be logged, serialised into evidence payloads, or returned to the browser.
 */
class EvidenceSource extends Model
{
    protected $fillable = [
        'name',
        'type',
        'enabled',
        'configuration',
        'schedule',
        'last_run_at',
        'owner_id',
    ];

    protected $casts = [
        'type' => EvidenceSourceType::class,
        'enabled' => 'boolean',
        'configuration' => 'encrypted:array',
        'last_run_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(EvidenceJob::class, 'source_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(EvidenceRecord::class, 'source_id');
    }

    public function validationRules(): HasMany
    {
        return $this->hasMany(ControlValidationRule::class, 'source_id');
    }

    /**
     * Operator-configured base URL. Validated by the connector before use; a
     * source must never fetch a URL supplied by an untrusted caller (SSRF).
     */
    public function baseUrl(): ?string
    {
        $value = $this->configuration['base_url'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    /**
     * Merge a submitted configuration over the stored one without erasing a
     * secret the form deliberately left blank.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    public static function mergeConfiguration(array $existing, array $submitted): array
    {
        if (blank($submitted['password'] ?? null)) {
            $submitted['password'] = $existing['password'] ?? null;
        }

        return array_merge($existing, $submitted);
    }
}
