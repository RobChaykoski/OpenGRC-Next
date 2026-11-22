<?php

namespace App\Models;

use App\Enums\VendorRiskRating;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A vendor's subcontractor, recorded without creating a second vendor record.
 *
 * The register answers "who else touches our data or service?" It is not a
 * supply-chain graph and does not model the subcontractor's own suppliers.
 */
class VendorSubcontractor extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'vendor_id',
        'name',
        'service',
        'country',
        'accesses_personal_data',
        'risk_rating',
        'last_reviewed_at',
        'notes',
    ];

    protected $casts = [
        'accesses_personal_data' => 'boolean',
        'risk_rating' => VendorRiskRating::class,
        'last_reviewed_at' => 'date',
    ];

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isReviewOverdue(int $months = 12): bool
    {
        return $this->last_reviewed_at === null
            || $this->last_reviewed_at->lt(today()->subMonths($months));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'country', 'accesses_personal_data', 'risk_rating'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
