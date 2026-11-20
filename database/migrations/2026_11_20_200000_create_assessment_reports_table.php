<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imported scanner reports (SARIF) and their findings.
 *
 * The report hash is the evidence; a finding is a deduplicated result linked
 * back to the report that produced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_reports')) {
            Schema::create('assessment_reports', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('format', 32)->default('sarif_json');
                $table->string('tool');
                $table->string('tool_version')->nullable();
                $table->char('report_sha256', 64);
                $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
                $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
                $table->unsignedInteger('finding_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();

                $table->index('report_sha256');
                $table->index('tool');
                $table->index('application_id');
                $table->index('asset_id');
            });
        }

        if (Schema::hasTable('findings') && ! Schema::hasColumn('findings', 'assessment_report_id')) {
            Schema::table('findings', function (Blueprint $table) {
                $table->foreignId('assessment_report_id')->nullable()->after('rule_id')
                    ->constrained('assessment_reports')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('findings') && Schema::hasColumn('findings', 'assessment_report_id')) {
            Schema::table('findings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assessment_report_id');
            });
        }

        Schema::dropIfExists('assessment_reports');
    }
};
