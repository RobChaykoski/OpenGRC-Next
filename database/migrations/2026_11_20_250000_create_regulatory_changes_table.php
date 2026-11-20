<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A human-maintained register of regulatory and standard changes.
 *
 * This is not a feed and does not store the source text. It records the change,
 * the link, the affected frameworks and the organisation's impact assessment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regulatory_changes')) {
            Schema::create('regulatory_changes', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('regulator');
                $table->string('reference_url', 512)->nullable();
                $table->date('published_at')->nullable();
                $table->date('effective_at')->nullable();
                $table->string('status', 32)->default('new');
                $table->string('impact', 32)->nullable();
                $table->text('summary')->nullable();
                $table->text('impact_assessment')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('assessed_at')->nullable();
                $table->date('due_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('impact');
                $table->index('due_at');
                $table->index(['status', 'due_at']);
            });
        }

        if (! Schema::hasTable('regulatory_change_standard')) {
            Schema::create('regulatory_change_standard', function (Blueprint $table) {
                $table->id();
                $table->foreignId('regulatory_change_id')->constrained('regulatory_changes')->cascadeOnDelete();
                $table->foreignId('standard_id')->constrained('standards')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['regulatory_change_id', 'standard_id'], 'reg_change_standard_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_change_standard');
        Schema::dropIfExists('regulatory_changes');
    }
};
