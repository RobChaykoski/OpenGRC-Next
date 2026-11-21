<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for the read-only assistant (ADR 0046).
 *
 * A run records the question, the deterministic query it resolved to, the shape
 * of the result, the narrative that was returned, and the model that produced
 * it. Retrieved rows are not stored again: the hash identifies the result, and
 * the question can be re-run against current data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_assistant_runs')) {
            return;
        }

        Schema::create('ai_assistant_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question', 500);
            $table->string('intent', 64)->nullable();
            $table->json('arguments')->nullable();
            $table->string('status', 32);
            $table->unsignedInteger('fact_count')->default(0);
            $table->json('counts')->nullable();
            $table->char('result_sha256', 64)->nullable();
            $table->text('answer')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('model', 96)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->json('injection_flags')->nullable();
            $table->string('notice', 500)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['intent', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_runs');
    }
};
