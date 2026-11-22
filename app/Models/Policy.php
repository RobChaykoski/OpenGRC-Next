<?php

namespace App\Models;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use App\Casts\SanitizedHtml;
use App\Contracts\EvidenceSubject;
use App\Enums\DocumentType;
use App\Models\Concerns\HasEvidenceLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Class Policy
 *
 * Represents an organizational policy in the GRC system.
 * Note: This is a Policy document model, not to be confused with Laravel authorization policies.
 */
class Policy extends Model implements EvidenceSubject
{
    use HasEvidenceLedger, HasFactory, HasTaxonomy, LogsActivity, SoftDeletes;

    public function evidencePathColumn(): string
    {
        return 'document_path';
    }

    protected $fillable = [
        'code',
        'name',
        'document_type',
        'policy_scope',
        'purpose',
        'body',
        'document_path',
        'scope_id',
        'department_id',
        'status_id',
        'owner_id',
        'effective_date',
        'retired_date',
        'revision_history',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'document_type' => DocumentType::class,
        'effective_date' => 'date',
        'retired_date' => 'date',
        'revision_history' => 'array',
        'purpose' => SanitizedHtml::class,
        'policy_scope' => SanitizedHtml::class,
        'body' => SanitizedHtml::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /**
     * Get the user who created this policy.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this policy.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the taxonomy scope for this policy.
     */
    public function scope(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'scope_id');
    }

    /**
     * Get the department taxonomy term for this policy.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'department_id');
    }

    /**
     * Get the status taxonomy term.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'status_id');
    }

    /**
     * Get the owner (user) of this policy.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the controls associated with this policy.
     */
    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_policy')
            ->withTimestamps();
    }

    /**
     * Canonical outcomes this policy or document supports.
     *
     * A policy can support outcomes directly even when it is not tied to an
     * imported control. Leave it empty when no reviewed match exists.
     */
    public function canonicalOutcomes(): BelongsToMany
    {
        return $this->belongsToMany(CanonicalControl::class, 'policy_canonical_outcome')
            ->withPivot('source')
            ->withTimestamps();
    }

    /**
     * Get the implementations associated with this policy.
     */
    public function implementations(): BelongsToMany
    {
        return $this->belongsToMany(Implementation::class, 'implementation_policy')
            ->withTimestamps();
    }

    /**
     * Get the risks associated with this policy.
     */
    public function risks(): BelongsToMany
    {
        return $this->belongsToMany(Risk::class, 'policy_risk')
            ->withTimestamps();
    }

    /**
     * Get the exceptions for this policy.
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(PolicyException::class);
    }

    /**
     * @return HasMany<PolicyAcknowledgement, $this>
     */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(PolicyAcknowledgement::class);
    }

    public function currentRevision(): string
    {
        return PolicyAcknowledgement::revisionFor($this);
    }

    public function isActive(): bool
    {
        $effective = $this->effective_date;
        $retired = $this->retired_date;

        if ($effective !== null && $effective->gt(today())) {
            return false;
        }

        return $retired === null || $retired->gte(today());
    }

    /**
     * Get the scope name accessor.
     */
    public function getScopeNameAttribute(): ?string
    {
        return $this->scope?->name;
    }

    /**
     * Get the status name accessor.
     */
    public function getStatusNameAttribute(): ?string
    {
        return $this->status?->name;
    }

    /**
     * Scope a query to filter by status.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeByStatus($query, string $statusName)
    {
        return $query->whereHas('status', function ($q) use ($statusName) {
            $q->where('name', $statusName);
        });
    }

    /**
     * Scope a query to filter by department.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope a query to filter by scope.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeByScope($query, string $scopeName)
    {
        return $query->whereHas('scope', function ($q) use ($scopeName) {
            $q->where('name', $scopeName);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'status_id', 'owner_id', 'retired_date'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
