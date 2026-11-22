<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assistant proposals as typed, approvable diffs (ADR 0055).
 *
 * The row holds the operations, the before/after values captured at proposal
 * time, and the human decision. It does not hold a free-form tool call: an
 * operation outside the catalogue is refused before it is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assistant_proposals')) {
            return;
        }

        Schema::create('assistant_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 64)->nullable();
            $table->string('summary', 255);
            $table->text('rationale');
            $table->json('operations');
            $table->string('state', 24)->default('proposed');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_reason', 255)->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable();
            $table->string('rollback_reason', 255)->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['state', 'created_at']);
            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_proposals');
    }
};
