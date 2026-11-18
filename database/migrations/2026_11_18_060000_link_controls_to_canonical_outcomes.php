<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link imported controls to the canonical outcomes they help satisfy.
 *
 * Many-to-many: one clause can serve several outcomes, and one outcome is
 * normally met by several clauses. `source` records whether a link came from
 * the curated config (`config` — refreshed by `opengrc:catalog:link`) or was
 * made by a person in the UI (`manual` — never touched by the command).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('control_canonical_outcome')) {
            Schema::create('control_canonical_outcome', function (Blueprint $table) {
                $table->id();
                $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
                $table->foreignId('canonical_control_id')->constrained('canonical_controls')->cascadeOnDelete();
                $table->string('source', 16)->default('manual');
                $table->timestamps();

                $table->unique(['control_id', 'canonical_control_id'], 'control_canonical_outcome_unique');
                $table->index('canonical_control_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('control_canonical_outcome');
    }
};
