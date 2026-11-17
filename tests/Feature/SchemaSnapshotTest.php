<?php

namespace Tests\Feature;

use App\Support\SchemaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the upgrade path: a schema change must be deliberate.
 *
 * Regenerate the fixture in the same change as an intentional migration:
 *
 *   UPDATE_SCHEMA_SNAPSHOT=1 php artisan test --filter=SchemaSnapshotTest
 */
class SchemaSnapshotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_database_schema_matches_the_committed_snapshot(): void
    {
        if (getenv('UPDATE_SCHEMA_SNAPSHOT')) {
            SchemaSnapshot::writeFixture();
            $this->markTestSkipped('Schema snapshot updated.');
        }

        $this->assertTrue(
            File::exists(SchemaSnapshot::fixturePath()),
            'Schema snapshot fixture is missing. Generate it with UPDATE_SCHEMA_SNAPSHOT=1.'
        );

        $expected = json_decode(File::get(SchemaSnapshot::fixturePath()), true);
        $actual = SchemaSnapshot::capture();

        $this->assertIsArray($expected);

        $added = array_diff(array_keys($actual), array_keys($expected));
        $removed = array_diff(array_keys($expected), array_keys($actual));

        $this->assertSame([], array_values($added), 'Tables were added: '.implode(', ', $added));
        $this->assertSame([], array_values($removed), 'Tables were removed: '.implode(', ', $removed));

        foreach ($actual as $table => $columns) {
            $this->assertSame(
                $expected[$table] ?? [],
                $columns,
                "Schema for [{$table}] changed. If intentional, regenerate the snapshot."
            );
        }
    }
}
