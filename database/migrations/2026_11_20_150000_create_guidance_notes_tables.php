<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guidance and advisory register.
 *
 * These are publications, not control frameworks. A note records the publisher,
 * reference, version, adoption state, owner and review date, and can be mapped
 * to the controls it informs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guidance_notes')) {
            Schema::create('guidance_notes', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('publisher');
                $table->string('reference', 64)->nullable();
                $table->string('version', 32)->nullable();
                $table->date('published_at')->nullable();
                $table->string('url', 512)->nullable();
                $table->string('licence', 128)->nullable();
                $table->string('status', 32)->default('new');
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('applicability')->nullable();
                $table->text('summary')->nullable();
                $table->date('reviewed_at')->nullable();
                $table->date('next_review_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('publisher');
                $table->index('status');
                $table->index('next_review_at');
            });
        }

        if (! Schema::hasTable('control_guidance_note')) {
            Schema::create('control_guidance_note', function (Blueprint $table) {
                $table->id();
                $table->foreignId('control_id')->constrained()->cascadeOnDelete();
                $table->foreignId('guidance_note_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['control_id', 'guidance_note_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('control_guidance_note');
        Schema::dropIfExists('guidance_notes');
    }
};
