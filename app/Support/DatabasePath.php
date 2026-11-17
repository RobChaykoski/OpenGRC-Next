<?php

namespace App\Support;

/**
 * Helpers for database paths that must survive a round trip through .env.
 *
 * Dotenv treats backslashes inside double-quoted values as escape sequences, so a
 * Windows path such as C:\app\database\opengrc.sqlite fails to parse. Forward
 * slashes are valid on every platform PHP supports, so we normalise on write and
 * detect the broken form on read.
 */
final class DatabasePath
{
    /**
     * Normalise a filesystem path for storage in a quoted environment value.
     */
    public static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Determine whether a configured value contains Windows separators.
     */
    public static function hasWindowsSeparators(?string $value): bool
    {
        return is_string($value) && str_contains($value, '\\');
    }
}
