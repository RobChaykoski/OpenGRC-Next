<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A daily assurance score for one framework, with its components.
 */
class AssuranceSnapshot extends Model
{
    protected $fillable = [
        'framework_code',
        'snapshot_date',
        'score',
        'components',
        'counts',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'score' => 'integer',
        'components' => 'array',
        'counts' => 'array',
    ];
}
