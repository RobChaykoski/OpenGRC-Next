<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third-party assurance reports and attestations.
 *
 * These are reports about controls, not control catalogues: a SOC 2 report, an
 * ISO certificate, a PCI AOC, a CMMC affirmation. The register keeps the period,
 * scope, auditor, opinion, exceptions, bridge letter and review date so the
 * assurance claim is inspectable without storing the report body.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attestation_reports')) {
            Schema::create('attestation_reports', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 32);
                $table->string('provider')->nullable();
                $table->text('subject')->nullable();
                $table->string('framework_code', 64)->nullable();
                $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->date('bridge_letter_date')->nullable();
                $table->date('next_review_at')->nullable();
                $table->string('outcome', 32)->default('not_assessed');
                $table->string('status', 32)->default('current');
                $table->text('summary')->nullable();
                $table->text('exceptions')->nullable();
                $table->string('document_reference')->nullable();
                $table->char('document_sha256', 64)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('type');
                $table->index('status');
                $table->index('expires_at');
                $table->index('next_review_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attestation_reports');
    }
};
