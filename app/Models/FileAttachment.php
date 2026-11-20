<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Contracts\EvidenceSubject;
use App\Models\Concerns\HasEvidenceLedger;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Class FileAttachment
 *
 * @property int $id
 * @property string $file_name
 * @property string $file_path
 * @property int $file_size
 * @property int $uploaded_by
 * @property int $data_request_id
 * @property int $audit_id
 * @property int|null $implementation_id
 * @property int $data_request_response_id
 * @property-read DataRequest $dataRequest
 *
 * @method static Builder|FileAttachment newModelQuery()
 * @method static Builder|FileAttachment newQuery()
 * @method static Builder|FileAttachment query()
 * @method static Builder|FileAttachment whereFileName($value)
 * @method static Builder|FileAttachment whereFilePath($value)
 * @method static Builder|FileAttachment whereFileSize($value)
 * @method static Builder|FileAttachment whereUploadedBy($value)
 * @method static Builder|FileAttachment whereDataRequestId($value)
 * @method static Builder|FileAttachment whereAuditId($value)
 * @method static Builder|FileAttachment whereDataRequestResponseId($value)
 *
 * @mixin Eloquent
 */
class FileAttachment extends Model implements EvidenceSubject
{
    use HasEvidenceLedger, LogsActivity;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'description' => SanitizedHtml::class,
    ];

    protected $hidden = [
        'file_path',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'file_name',
        'file_path',
        'file_size',
        'description',
        'uploaded_by',
        'data_request_id',
        'audit_id',
        'implementation_id',
        'data_request_response_id',
    ];

    /**
     * Get the data request that owns the file attachment.
     */
    public function dataRequest(): BelongsTo
    {
        return $this->belongsTo(DataRequest::class);
    }

    public function dataRequestResponse(): BelongsTo
    {
        return $this->belongsTo(DataRequestResponse::class);
    }

    public function auditItem(): BelongsTo
    {
        return $this->belongsTo(AuditItem::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * Human evidence attached directly to a control implementation.
     *
     * @return BelongsTo<Implementation, $this>
     */
    public function implementation(): BelongsTo
    {
        return $this->belongsTo(Implementation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['file_name', 'file_size', 'uploaded_by', 'data_request_id', 'audit_id', 'data_request_response_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
