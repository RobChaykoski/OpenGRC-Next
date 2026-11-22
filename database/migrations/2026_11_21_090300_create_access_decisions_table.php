<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of runtime access decisions (ADR 0049).
 *
 * A decision is evidence. It stores the classification snapshot, the asserted
 * context, the matched policy version and a hash chain over the previous
 * entry. It never stores the data that was requested, a raw IP address or a
 * device identifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('access_decisions')) {
            return;
        }

        Schema::create('access_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('correlation_id', 64)->unique();
            $table->string('subject_type', 24)->default('user');
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_label', 191);
            $table->foreignId('protected_resource_id')->nullable()->constrained('protected_resources')->nullOnDelete();
            $table->string('resource_ref', 191);
            $table->string('tier', 16);
            $table->string('profile', 24);
            $table->string('purpose_code', 32)->nullable();
            $table->json('context')->nullable();
            $table->foreignId('matched_policy_id')->nullable()->constrained('access_policies')->nullOnDelete();
            $table->unsignedInteger('policy_version')->nullable();
            $table->string('rules_hash', 64)->nullable();
            $table->string('action', 24);
            $table->string('reason', 32);
            $table->unsignedInteger('ttl_seconds')->nullable();
            $table->unsignedInteger('risk_weight')->default(0);
            $table->string('pep', 64)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('decided_at');
            $table->string('previous_hash', 64)->nullable();
            $table->string('entry_hash', 64);
            $table->timestamps();

            $table->index('decided_at');
            $table->index(['subject_type', 'subject_label', 'decided_at']);
            $table->index('action');
            $table->index(['tier', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_decisions');
    }
};
