<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class MigrationManifest
{
    public const VERSION = 1;

    public static function path(): string
    {
        return database_path('migration-manifest.json');
    }

    /**
     * @return array<string, string> migration name => sha256
     */
    public static function checksums(): array
    {
        $checksums = [];

        foreach (File::glob(database_path('migrations/*.php')) ?: [] as $file) {
            $checksums[basename($file, '.php')] = hash_file('sha256', $file);
        }

        ksort($checksums);

        return $checksums;
    }

    public static function generate(): int
    {
        $migrations = [];

        foreach (self::checksums() as $name => $checksum) {
            $migrations[] = ['name' => $name, 'sha256' => $checksum];
        }

        File::put(self::path(), json_encode([
            'version' => self::VERSION,
            'migrations' => $migrations,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return count($migrations);
    }

    /**
     * @return array<int, string>
     */
    public static function verifyFiles(): array
    {
        if (! File::exists(self::path())) {
            return ['Migration manifest is missing. Run php artisan opengrc:migrations:manifest.'];
        }

        $manifest = json_decode((string) File::get(self::path()), true);

        if (! is_array($manifest) || ($manifest['version'] ?? null) !== self::VERSION) {
            return ['Migration manifest has an unsupported format.'];
        }

        $expected = [];

        foreach ($manifest['migrations'] ?? [] as $migration) {
            if (! isset($migration['name'], $migration['sha256'])) {
                return ['Migration manifest contains an invalid entry.'];
            }

            $expected[(string) $migration['name']] = (string) $migration['sha256'];
        }

        $actual = self::checksums();
        $errors = [];

        foreach ($expected as $name => $checksum) {
            if (! isset($actual[$name])) {
                $errors[] = "Migration {$name} is missing from database/migrations.";
            } elseif (! hash_equals($checksum, $actual[$name])) {
                $errors[] = "Migration {$name} has changed since the manifest was generated.";
            }
        }

        foreach ($actual as $name => $checksum) {
            if (! isset($expected[$name])) {
                $errors[] = "Migration {$name} is not in the manifest. Regenerate it in the same change.";
            }
        }

        return $errors;
    }
}
