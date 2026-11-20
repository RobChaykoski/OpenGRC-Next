<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A multi-step approval request.
 *
 * The request is the record; the steps are an ordered chain. A step is approved
 * or rejected by its named approver, and the request completes when the chain
 * does. This is deliberately not a per-field workflow engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 32)->default('pending');
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'due_at']);
            });
        }

        if (! Schema::hasTable('approval_request_steps')) {
            Schema::create('approval_request_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
                $table->unsignedInteger('sequence');
                $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 32)->default('pending');
                $table->timestamp('decided_at')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique(['approval_request_id', 'sequence'], 'approval_request_step_sequence_unique');
                $table->index(['approver_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_steps');
        Schema::dropIfExists('approval_requests');
    }
};
