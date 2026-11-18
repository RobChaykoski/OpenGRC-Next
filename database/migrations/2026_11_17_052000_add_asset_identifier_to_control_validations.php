<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validation history must distinguish unmapped hosts.
 *
 * Evidence can arrive before the asset inventory knows the host. In that case
 * asset_id is null for every record; without a stable identifier the validator
 * merges the whole fleet into one row. The connector's external id (for
 * PatchMon, patchmon:<hostname>) is that identifier until an asset is linked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('control_validations', function (Blueprint $table) {
            $table->string('asset_identifier', 191)->nullable();
            $table->index(['rule_id', 'asset_identifier']);
        });
    }

    public function down(): void
    {
        Schema::table('control_validations', function (Blueprint $table) {
            $table->dropIndex(['rule_id', 'asset_identifier']);
            $table->dropColumn('asset_identifier');
        });
    }
};
