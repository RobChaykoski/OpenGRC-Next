<?php

namespace App\Support;

final class InstanceMode
{
    public const MODE_KEY = 'instance.mode';

    public const ACTIVATED_AT_KEY = 'instance.production_at';

    public const ACTIVATED_BY_KEY = 'instance.production_by';

    public const REASON_KEY = 'instance.production_reason';

    /**
     * Production mode is a one-way latch. Until it is set explicitly, the
     * environment decides: production enforces evidence immutability, everything
     * else stays convenient for development and tests.
     */
    public static function isProduction(): bool
    {
        try {
            $mode = setting(self::MODE_KEY);

            if (filled($mode)) {
                return $mode === 'production';
            }
        } catch (\Throwable) {
            // Settings may not be available during install or early boot.
        }

        return app()->environment('production');
    }

    public static function isExplicitlyProduction(): bool
    {
        try {
            return setting(self::MODE_KEY) === 'production';
        } catch (\Throwable) {
            return false;
        }
    }

    public static function activateProduction(?int $userId, string $reason): void
    {
        setting([
            self::MODE_KEY => 'production',
            self::ACTIVATED_AT_KEY => now()->toIso8601String(),
            self::ACTIVATED_BY_KEY => $userId,
            self::REASON_KEY => $reason,
        ]);
    }
}
