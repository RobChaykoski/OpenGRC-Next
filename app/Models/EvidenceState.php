<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EvidenceState extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'status',
        'reason',
        'actor_type',
        'actor_id',
        'changed_at',
        'superseded_by_type',
        'superseded_by_id',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function supersededBy(): MorphTo
    {
        return $this->morphTo();
    }
}
