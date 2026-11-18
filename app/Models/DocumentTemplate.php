<?php

namespace App\Models;

use App\Enums\DocumentClassification;
use App\Enums\DocumentTemplateStatus;
use App\Enums\DocumentTemplateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable DOCX template. The original file is private; extracted text is
 * encrypted at rest and placeholders are kept separately for review.
 */
class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'version',
        'status',
        'classification',
        'owner_id',
        'policy_id',
        'source_file',
        'source_sha256',
        'body_text',
        'placeholders',
        'outline',
        'approved_at',
        'approved_by',
        'retention_until',
    ];

    protected $casts = [
        'type' => DocumentTemplateType::class,
        'status' => DocumentTemplateStatus::class,
        'classification' => DocumentClassification::class,
        'body_text' => 'encrypted',
        'placeholders' => 'array',
        'outline' => 'array',
        'approved_at' => 'datetime',
        'retention_until' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === DocumentTemplateStatus::Approved;
    }

    public function requiresWriter(): bool
    {
        return $this->classification instanceof DocumentClassification
            ? $this->classification->requiresWriter()
            : false;
    }

    /**
     * @return array<int, string>
     */
    public function placeholderList(): array
    {
        return is_array($this->placeholders) ? array_values($this->placeholders) : [];
    }
}
