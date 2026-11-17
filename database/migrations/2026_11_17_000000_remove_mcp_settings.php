<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove MCP settings left behind by the feature removal.
     *
     * The original seeder migration stays in history; this is the forward-only
     * cleanup that removes the rows from existing installations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('key', 'like', 'mcp.%')->delete();
    }

    /**
     * Restore the default disabled flag if the migration is rolled back.
     */
    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'mcp.enabled'],
            ['value' => 'false', 'created_at' => now(), 'updated_at' => now()]
        );
    }
};
