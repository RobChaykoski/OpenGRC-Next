<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link an artifact finding back to the register row (ADR 0059).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('findings') || Schema::hasColumn('findings', 'model_artifact_id')) {
            return;
        }

        Schema::table('findings', function (Blueprint $table) {
            $table->foreignId('model_artifact_id')
                ->nullable()
                ->after('asset_id')
                ->constrained('model_artifacts')
                ->nullOnDelete();
            $table->index(['model_artifact_id', 'status'], 'findings_model_artifact_status_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('findings', 'model_artifact_id')) {
            return;
        }

        Schema::table('findings', function (Blueprint $table) {
            $table->dropForeign(['model_artifact_id']);
            $table->dropIndex('findings_model_artifact_status_index');
            $table->dropColumn('model_artifact_id');
        });
    }
};
