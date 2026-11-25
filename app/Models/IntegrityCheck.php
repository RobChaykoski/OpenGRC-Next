<?php

namespace App\Models;

use App\Enums\IntegrityCheckStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One integrity verification run.
 *
 * The evidence and audit ledgers are the record of what happened. This model is
 * the record that someone checked them. It is not an audit ledger entry and is
 * deliberately not activity-logged: a daily check must not fill the ledger it
 * is checking with its own noise.
 */
class IntegrityCheck extends Model
{
    protected $fillable = [
        'check',
        'status',
        'summary',
        'detail',
        'duration_ms',
        'checked_at',
    ];

    protected $casts = [
        'status' => IntegrityCheckStatus::class,
        'detail' => 'array',
        'duration_ms' => 'integer',
        'checked_at' => 'datetime',
    ];

    /**
     * @param  Builder<IntegrityCheck>  $query
     * @return Builder<IntegrityCheck>
     */
    public function scopeForCheck(Builder $query, string $check): Builder
    {
        return $query->where('check', $check);
    }

    public static function latestFor(string $check): ?self
    {
        return self::query()
            ->forCheck($check)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();
    }

    public function failed(): bool
    {
        return $this->status === IntegrityCheckStatus::Fail;
    }

    public function warned(): bool
    {
        return $this->status === IntegrityCheckStatus::Warn;
    }
}
