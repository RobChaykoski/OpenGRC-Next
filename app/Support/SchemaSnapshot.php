<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * A stable, minimal description of the database schema.
 *
 * Comparing this against a committed fixture turns an accidental schema change
 * into a failing test instead of a production surprise during an upgrade.
 * Only the parts that matter for compatibility are recorded: table names, column
 * names, normalised types, and nullability.
 */
final class SchemaSnapshot
{
    /**
     * Capture the current schema.
     *
     * @return array<string, array<int, array{name: string, type: string, nullable: bool}>>
     */
    public static function capture(): array
    {
        $tables = collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn (string $name) => str_starts_with($name, 'sqlite_'))
            ->sort()
            ->values();

        $snapshot = [];

        foreach ($tables as $table) {
            $columns = collect(Schema::getColumns($table))
                ->map(fn (array $column) => [
                    'name' => (string) $column['name'],
                    'type' => strtolower((string) ($column['type_name'] ?? $column['type'] ?? '')),
                    'nullable' => (bool) ($column['nullable'] ?? false),
                ])
                ->sortBy('name')
                ->values()
                ->all();

            $snapshot[$table] = $columns;
        }

        return $snapshot;
    }

    /**
     * Path to the committed fixture.
     */
    public static function fixturePath(): string
    {
        return base_path('tests/Fixtures/schema-snapshot.json');
    }

    /**
     * Write the current schema to the fixture.
     */
    public static function writeFixture(): void
    {
        $directory = dirname(self::fixturePath());

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            self::fixturePath(),
            json_encode(self::capture(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }
}
