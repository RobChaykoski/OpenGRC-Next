<?php

namespace App\Models;

use App\Enums\ExerciseSource;
use App\Enums\ExerciseStatus;
use App\Enums\ExerciseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tabletop, simulation or red-team exercise.
 *
 * The scenario package stays upstream. The register records the objectives,
 * participants (roles, not personal profiles), findings and corrective actions.
 */
class Exercise extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'source',
        'source_reference',
        'scenario',
        'objectives',
        'scope',
        'scheduled_at',
        'conducted_at',
        'status',
        'facilitator_id',
        'participants',
        'findings_summary',
        'corrective_actions',
        'evidence_reference',
        'evidence_sha256',
        'notes',
    ];

    protected $casts = [
        'type' => ExerciseType::class,
        'source' => ExerciseSource::class,
        'status' => ExerciseStatus::class,
        'scheduled_at' => 'date',
        'conducted_at' => 'date',
        'participants' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function isPlanned(): bool
    {
        return $this->status === ExerciseStatus::Planned;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExerciseStatus::Completed;
    }

    public function reference(): string
    {
        return 'EX-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }
}
