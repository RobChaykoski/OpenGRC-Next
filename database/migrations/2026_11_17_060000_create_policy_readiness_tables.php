<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy readiness assessment (imported from a policy checklist workbook).
 *
 * The workbook tracks three independent dimensions per policy: documentation,
 * implementation, and effectiveness. They are stored separately because a
 * document can be approved while the control is still unimplemented.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('policy_readiness_assessments')) {
            Schema::create('policy_readiness_assessments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('source_file')->nullable();
                $table->char('source_sha256', 64)->nullable();
                $table->string('revision', 64)->nullable();
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('policy_readiness_items')) {
            Schema::create('policy_readiness_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_id')->constrained('policy_readiness_assessments')->cascadeOnDelete();
                $table->foreignId('policy_id')->nullable()->constrained('policies')->nullOnDelete();
                $table->string('code', 32);
                $table->string('category', 64)->nullable();
                $table->string('title', 512);
                $table->string('priority', 32)->nullable();
                $table->text('framework_applicability')->nullable();
                $table->text('nist_csf')->nullable();
                $table->text('itsg33')->nullable();
                $table->text('cmmc')->nullable();
                $table->text('objective')->nullable();
                $table->text('expected_evidence')->nullable();
                $table->string('applicability', 32)->nullable();
                $table->string('documentation_status', 32)->default('not_started');
                $table->string('implementation_status', 32)->default('not_planned');
                $table->string('effectiveness_status', 32)->default('not_tested');
                $table->string('owner', 255)->nullable();
                $table->string('target_date', 64)->nullable();
                $table->string('last_reviewed', 64)->nullable();
                $table->string('evidence_location', 512)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['assessment_id', 'code']);
                $table->index(['documentation_status', 'implementation_status', 'effectiveness_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_readiness_items');
        Schema::dropIfExists('policy_readiness_assessments');
    }
};
