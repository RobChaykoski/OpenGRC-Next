<?php

namespace App\Models;

use App\Enums\AssistantIntent;
use App\Enums\AssistantRunStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question asked of the read-only assistant (ADR 0046).
 *
 * The row is the accountability record: actor, question, resolved query,
 * arguments, result counts and hash, narrative, model and tokens. Retrieved rows
 * are deliberately not stored again; the hash identifies the result and the
 * question can be re-run.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $question
 * @property AssistantIntent|null $intent
 * @property array|null $arguments
 * @property AssistantRunStatus $status
 * @property int $fact_count
 * @property array|null $counts
 * @property string|null $result_sha256
 * @property string|null $answer
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property array|null $injection_flags
 * @property string|null $notice
 * @property string|null $error
 * @property-read User|null $user
 */
class AiAssistantRun extends Model
{
    protected $fillable = [
        'user_id',
        'question',
        'intent',
        'arguments',
        'status',
        'fact_count',
        'counts',
        'result_sha256',
        'answer',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'injection_flags',
        'notice',
        'error',
    ];

    protected $casts = [
        'intent' => AssistantIntent::class,
        'arguments' => 'array',
        'status' => AssistantRunStatus::class,
        'fact_count' => 'integer',
        'counts' => 'array',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'injection_flags' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<AiAssistantRun>  $query
     * @return Builder<AiAssistantRun>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    public function hasInjectionFlags(): bool
    {
        return is_array($this->injection_flags) && $this->injection_flags !== [];
    }
}
