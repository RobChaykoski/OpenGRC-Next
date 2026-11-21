<?php

namespace App\Models;

use App\Services\AppLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A scoped, read-only link to one audit (ADR 0045).
 *
 * The raw token is returned once, when the grant is issued or rotated. Only its
 * SHA-256 is persisted. A grant is valid until it is revoked or expires; there
 * is no sliding renewal, because an expired link should need a deliberate new
 * decision rather than quietly staying alive.
 *
 * @property int $id
 * @property int $audit_id
 * @property string $label
 * @property string $token_hash
 * @property int|null $created_by
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon|null $last_accessed_at
 * @property int $access_count
 * @property-read Audit $audit
 * @property-read User|null $creator
 * @property-read User|null $revoker
 * @property-read Collection<int, AuditPortalView> $views
 */
class AuditPortalGrant extends Model
{
    use HasFactory, LogsActivity;

    public const TOKEN_LENGTH = 64;

    protected $fillable = [
        'audit_id',
        'label',
        'token_hash',
        'created_by',
        'expires_at',
        'revoked_at',
        'revoked_by',
        'last_accessed_at',
        'access_count',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * The raw token, set only for the request that issued or rotated it.
     *
     * It is deliberately not a column. Once the response is sent the platform
     * cannot show the link again; an administrator rotates it instead.
     */
    public ?string $plainToken = null;

    /**
     * @return BelongsTo<Audit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * @return HasMany<AuditPortalView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(AuditPortalView::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Issue a new link. The returned model carries the raw token in
     * $plainToken for the life of the request.
     */
    public static function issue(
        Audit $audit,
        string $label,
        CarbonInterface $expiresAt,
        ?User $creator = null,
    ): self {
        $token = Str::random(self::TOKEN_LENGTH);

        $grant = static::create([
            'audit_id' => $audit->getKey(),
            'label' => $label,
            'token_hash' => self::hashToken($token),
            'created_by' => $creator?->getKey(),
            'expires_at' => $expiresAt,
        ]);

        $grant->plainToken = $token;

        AppLogger::info(
            category: 'audit_portal',
            event: 'GrantIssued',
            message: 'Read-only auditor portal link issued',
            context: [
                'audit_portal_grant_id' => $grant->getKey(),
                'audit_id' => $audit->getKey(),
                'expires_at' => $grant->expires_at->toIso8601String(),
            ],
            subject: $grant,
            causer: $creator,
        );

        return $grant;
    }

    /**
     * Replace the token, invalidating any link already shared. A lost link is
     * rotated, not recovered.
     */
    public function rotate(string $label, CarbonInterface $expiresAt, ?User $actor = null): string
    {
        $token = Str::random(self::TOKEN_LENGTH);

        $this->forceFill([
            'label' => $label,
            'token_hash' => self::hashToken($token),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'revoked_by' => null,
            'access_count' => 0,
            'last_accessed_at' => null,
        ])->save();

        $this->plainToken = $token;

        AppLogger::info(
            category: 'audit_portal',
            event: 'GrantRotated',
            message: 'Read-only auditor portal link rotated',
            context: [
                'audit_portal_grant_id' => $this->getKey(),
                'audit_id' => $this->audit_id,
                'expires_at' => $this->expires_at->toIso8601String(),
            ],
            subject: $this,
            causer: $actor,
        );

        return $token;
    }

    public function revoke(?User $actor = null, ?string $reason = null): void
    {
        $this->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $actor?->getKey(),
        ])->save();

        AppLogger::info(
            category: 'audit_portal',
            event: 'GrantRevoked',
            message: 'Read-only auditor portal link revoked',
            context: [
                'audit_portal_grant_id' => $this->getKey(),
                'audit_id' => $this->audit_id,
                'reason' => $reason,
            ],
            subject: $this,
            causer: $actor,
        );
    }

    public static function findByToken(string $token): ?self
    {
        if (strlen($token) !== self::TOKEN_LENGTH || ! ctype_alnum($token)) {
            return null;
        }

        return static::query()
            ->where('token_hash', self::hashToken($token))
            ->first();
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * A display state, not the storage state: revoked wins over expired because
     * revocation is the deliberate act an auditor may ask about.
     */
    public function state(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }

        return $this->expires_at->isFuture() ? 'active' : 'expired';
    }

    public function recordView(Request $request): AuditPortalView
    {
        $view = $this->views()->create([
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'viewed_at' => now(),
        ]);

        $this->forceFill([
            'last_accessed_at' => now(),
            'access_count' => $this->access_count + 1,
        ])->save();

        return $view;
    }

    /**
     * @param  Builder<AuditPortalGrant>  $query
     * @return Builder<AuditPortalGrant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['label', 'expires_at', 'revoked_at', 'revoked_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
