<?php

namespace App\Models;

use App\Enums\ResponseStatus;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Kirschbaum\Commentions\Contracts\Commenter;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property Carbon|null $last_activity
 */
class User extends Authenticatable implements Commenter, FilamentUser
{
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, softDeletes, TwoFactorAuthenticatable;

    protected static $logOnlyDirty = true;

    protected static $logName = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'text',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be guarded from mass assignment.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'last_activity',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_activity' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
    ];

    /**
     * Update the user's last activity timestamp.
     */
    public function updateLastActivity(): void
    {
        DB::table('users')
            ->where('id', $this->id)
            ->update(['last_activity' => now()]);

    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * The role, defaulted for legacy rows that predate the role column.
     */
    public function role(): UserRole
    {
        return $this->getAttribute('role') instanceof UserRole
            ? $this->getAttribute('role')
            : UserRole::Viewer;
    }

    public function isAdmin(): bool
    {
        return $this->role()->canManage();
    }

    public function canWrite(): bool
    {
        return $this->role()->canWrite();
    }

    public function canDeleteRecords(): bool
    {
        return $this->role()->canDelete();
    }

    public function audits(): BelongsToMany
    {
        return $this->belongsToMany(Audit::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(DataRequestResponse::class, 'requestee_id');
    }

    public function openTodos(): HasMany
    {
        return $this->hasMany(DataRequestResponse::class, 'requestee_id')
            ->whereIn('status', [ResponseStatus::PENDING, ResponseStatus::REJECTED]);
    }

    public function managedPrograms(): HasMany
    {
        return $this->hasMany(Program::class, 'program_manager_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the display name, appending (Deactivated) for soft-deleted users.
     */
    public function displayName(): string
    {
        return $this->trashed() ? $this->name.' (Deactivated)' : $this->name;
    }

    /**
     * Get active (non-deleted) user options for select fields.
     *
     * @return array<int, string>
     */
    public static function activeOptions(): array
    {
        return static::whereNotNull('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get user options for select fields, including soft-deleted users with (Deactivated) label.
     *
     * @return array<int, string>
     */
    public static function optionsWithDeactivated(): array
    {
        return static::withTrashed()
            ->whereNotNull('name')
            ->get()
            ->mapWithKeys(fn (User $user) => [
                $user->id => $user->trashed() ? "{$user->name} (Deactivated)" : $user->name,
            ])
            ->toArray();
    }
}
