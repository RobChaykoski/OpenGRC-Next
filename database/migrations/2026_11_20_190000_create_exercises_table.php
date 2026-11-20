<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exercise register: tabletops, simulations and red-team exercises.
 *
 * The scenario package stays upstream. The register records the objectives,
 * participants, findings and corrective actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exercises')) {
            Schema::create('exercises', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 32);
                $table->string('source', 32)->default('internal');
                $table->string('source_reference', 128)->nullable();
                $table->text('scenario')->nullable();
                $table->text('objectives')->nullable();
                $table->text('scope')->nullable();
                $table->date('scheduled_at')->nullable();
                $table->date('conducted_at')->nullable();
                $table->string('status', 32)->default('planned');
                $table->foreignId('facilitator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('participants')->nullable();
                $table->text('findings_summary')->nullable();
                $table->text('corrective_actions')->nullable();
                $table->string('evidence_reference')->nullable();
                $table->char('evidence_sha256', 64)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('type');
                $table->index('status');
                $table->index('scheduled_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
