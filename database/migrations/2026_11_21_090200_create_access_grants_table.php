<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Just-in-time access grants (ADR 0049).
 *
 * A grant is an entitlement with a clock, not a workflow step. Break-glass
 * grants carry a clinical reason, a capped TTL and a mandatory privacy review
 * with an owner and a due date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('access_grants')) {
            return;
        }

        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 24)->default('user');
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_label', 191);
            $table->foreignId('protected_resource_id')->nullable()->constrained('protected_resources')->nullOnDelete();
            $table->foreignId('access_policy_id')->nullable()->constrained('access_policies')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose_code', 32);
            $table->text('justification');
            $table->boolean('is_break_glass')->default(false);
            $table->string('clinical_reason', 64)->nullable();
            $table->string('state', 24)->default('requested');
            $table->string('correlation_id', 64)->nullable()->unique();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_reason', 255)->nullable();
            $table->boolean('review_required')->default(false);
            $table->date('review_due_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_outcome', 24)->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('risk_id')->nullable()->constrained('risks')->nullOnDelete();
            $table->timestamps();

            $table->index(['state', 'expires_at']);
            $table->index(['subject_type', 'subject_label']);
            $table->index(['review_required', 'review_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_grants');
    }
};
