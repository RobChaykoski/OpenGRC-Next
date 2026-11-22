<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rule object of access governance (ADR 0049).
 *
 * A policy is data, not code: tier x profile x context -> action. Policies are
 * versioned and hashed so a decision can be reproduced after a rule change.
 * Precedence is priority (ascending) then specificity; explicit deny wins.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('access_policies')) {
            return;
        }

        Schema::create('access_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('tiers');
            $table->json('profiles')->nullable();
            $table->json('conditions')->nullable();
            $table->string('action', 24);
            $table->json('mask_columns')->nullable();
            $table->unsignedInteger('max_duration_minutes')->nullable();
            $table->boolean('requires_justification')->default(false);
            $table->boolean('requires_review')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('version')->default(1);
            $table->string('rules_hash', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('dry_run')->default(false);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('review_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_policies');
    }
};
