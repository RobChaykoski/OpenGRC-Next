<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remediation tasks linked to a finding, risk or control.
 *
 * A task is the work between a finding and a closed gap. It is not a full project
 * plan and does not model dependencies or milestones yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('remediation_tasks')) {
            return;
        }

        Schema::create('remediation_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->nullable()->constrained('findings')->nullOnDelete();
            $table->foreignId('risk_id')->nullable()->constrained('risks')->nullOnDelete();
            $table->foreignId('control_id')->nullable()->constrained('controls')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index('finding_id');
            $table->index('risk_id');
            $table->index('control_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remediation_tasks');
    }
};
