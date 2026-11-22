<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DLP suppressions with an expiry (ADR 0053).
 *
 * A suppression accepts a detector's output for a bounded scope and period. It
 * never deletes a finding: the finding stays, with its suppression visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlp_suppressions')) {
            return;
        }

        Schema::create('dlp_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dlp_policy_id')->constrained('dlp_policies')->cascadeOnDelete();
            $table->string('identifier_type', 64)->nullable();
            $table->string('match_type', 120)->nullable();
            $table->text('justification');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 16)->default('requested');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_reason', 255)->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['state', 'expires_at']);
            $table->index(['dlp_policy_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dlp_suppressions');
    }
};
