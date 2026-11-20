<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reviewed mapping between two imported controls.
 *
 * This is not textual equivalence and not evidence. It records why a reviewer
 * considers two requirements to cover the same programme intent, with a
 * confidence and an approval state.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('control_crosswalks')) {
            return;
        }

        Schema::create('control_crosswalks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_control_id')->constrained('controls')->cascadeOnDelete();
            $table->foreignId('target_control_id')->constrained('controls')->cascadeOnDelete();
            $table->text('rationale');
            $table->string('confidence', 16)->default('medium');
            $table->string('status', 16)->default('draft');
            $table->text('review_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_control_id', 'target_control_id'], 'control_crosswalk_pair_unique');
            $table->index(['status', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_crosswalks');
    }
};
