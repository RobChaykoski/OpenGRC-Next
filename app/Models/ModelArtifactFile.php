<?php

namespace App\Models;

use App\Enums\ModelArtifactFileKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file inside a model artifact (ADR 0059).
 *
 * The path is relative to the artifact root so the same directory can be
 * registered from a different host without rewriting the record.
 */
class ModelArtifactFile extends Model
{
    protected $fillable = [
        'model_artifact_id',
        'path',
        'kind',
        'size_bytes',
        'sha256',
    ];

    protected $casts = [
        'kind' => ModelArtifactFileKind::class,
        'size_bytes' => 'integer',
    ];

    /**
     * @return BelongsTo<ModelArtifact, $this>
     */
    public function artifact(): BelongsTo
    {
        return $this->belongsTo(ModelArtifact::class, 'model_artifact_id');
    }
}
