<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validation is per asset, not per control as a whole.
 *
 * A rule that covers a fleet must record which host failed; otherwise a single
 * passing host hides the rest. The original table only had control and source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('control_validations', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->index(['control_id', 'asset_id', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('control_validations', function (Blueprint $table) {
            $table->dropIndex(['control_id', 'asset_id', 'evaluated_at']);
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
