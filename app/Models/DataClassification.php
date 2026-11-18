<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataClassification extends Model
{
    protected $fillable = [
        'code',
        'label',
        'level',
        'description',
        'industries',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'industries' => 'array',
        'is_system' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForIndustry(Builder $query, ?string $industry): Builder
    {
        if (blank($industry)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($industry): void {
            $query->whereNull('industries')
                ->orWhereJsonContains('industries', $industry);
        });
    }

    public function appliesTo(string $industry): bool
    {
        return $this->industries === null || in_array($industry, $this->industries, true);
    }

    public function levelRank(): int
    {
        return (int) (config("data_classification.levels.{$this->level}.rank") ?? 2);
    }
}
