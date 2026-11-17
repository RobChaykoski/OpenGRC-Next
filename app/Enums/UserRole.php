<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The three roles a deployment needs.
 *
 * The previous model had 70+ generated permission strings, 30 policy classes,
 * and a role-permission matrix to maintain. This trades granularity for a model
 * that can actually be kept correct: read, write, administer.
 */
enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Viewer => 'Viewer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access, including settings, users, and deletions',
            self::Editor => 'Create and update records; cannot delete or change settings',
            self::Viewer => 'Read-only access to records',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Admin => 3,
            self::Editor => 2,
            self::Viewer => 1,
        };
    }

    public function canWrite(): bool
    {
        return $this->rank() >= self::Editor->rank();
    }

    public function canDelete(): bool
    {
        return $this === self::Admin;
    }

    public function canManage(): bool
    {
        return $this === self::Admin;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->getLabel()])
            ->all();
    }

    /**
     * Map legacy role names (Spatie era) or existing values onto a role.
     */
    public static function fromLegacy(string|int|null $value): self
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return self::Viewer;
        }

        return match (strtolower(trim((string) $value))) {
            'admin', 'super admin', 'security admin' => self::Admin,
            'editor' => self::Editor,
            default => self::Viewer,
        };
    }
}
