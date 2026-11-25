<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The inference runtime that serves an artifact (ADR 0059, section 5).
 *
 * The runtime is deployment context, not a property of the bytes: the same
 * artifact can be served by a different runtime after an upgrade. It is stored
 * on the register row so the unsupported-runtime finding has a subject, and it
 * is recorded separately from registration so a version bump does not require
 * re-hashing the weights.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('model_artifacts', 'runtime')) {
            return;
        }

        Schema::table('model_artifacts', function (Blueprint $table) {
            $table->string('runtime', 64)->nullable()->after('application_id');
            $table->string('runtime_version', 64)->nullable()->after('runtime');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('model_artifacts', 'runtime')) {
            return;
        }

        Schema::table('model_artifacts', function (Blueprint $table) {
            $table->dropColumn(['runtime', 'runtime_version']);
        });
    }
};
