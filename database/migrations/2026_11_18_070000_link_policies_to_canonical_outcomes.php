<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link policies and other documents to the canonical outcomes they support.
 *
 * Policies are local records, not imported reference data, so links are made by
 * people in the UI rather than seeded from a curated map. The `source` column is
 * kept for symmetry with control links and future curated document registers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('policy_canonical_outcome')) {
            Schema::create('policy_canonical_outcome', function (Blueprint $table) {
                $table->id();
                $table->foreignId('policy_id')->constrained('policies')->cascadeOnDelete();
                $table->foreignId('canonical_control_id')->constrained('canonical_controls')->cascadeOnDelete();
                $table->string('source', 16)->default('manual');
                $table->timestamps();

                $table->unique(['policy_id', 'canonical_control_id'], 'policy_canonical_outcome_unique');
                $table->index('canonical_control_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_canonical_outcome');
    }
};
