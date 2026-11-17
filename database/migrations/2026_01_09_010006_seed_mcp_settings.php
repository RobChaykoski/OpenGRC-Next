<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * MCP was removed from the community edition; this migration is retained so
     * existing migration histories still run. See
     * docs/decisions/0006-remove-mcp.md and the later cleanup migration that
     * deletes any remaining mcp.* settings.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
