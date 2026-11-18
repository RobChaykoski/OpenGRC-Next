<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Findings as a first-class object (closes docs/grc/README.md gap 2).
 *
 * A failed validation raises one finding per rule and asset. Repeat failures
 * update that finding instead of creating noise; a passing validation resolves
 * it. Manual findings are never touched by automated checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('findings')) {
            return;
        }

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('severity', 16)->default('medium');
            $table->string('status', 32)->default('open');
            $table->string('source', 16)->default('manual');
            $table->foreignId('control_id')->nullable()->constrained('controls')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('evidence_source_id')->nullable()->constrained('evidence_sources')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('control_validation_rules')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            // Stable key for automated findings: rule + source + asset.
            $table->string('dedupe_key', 191)->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('identified_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['dedupe_key', 'status']);
            $table->index(['status', 'severity']);
            $table->index(['control_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
