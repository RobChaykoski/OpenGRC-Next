<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SBOM inventory and OSV advisory evidence.
 *
 * The SBOM document is a snapshot; a component is a package coordinate; a
 * vulnerability is an advisory hit against one component. The report and its
 * hash are the evidence, not a live scanner state.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sbom_documents')) {
            Schema::create('sbom_documents', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('format', 32);
                $table->string('spec_version', 32)->nullable();
                $table->char('document_sha256', 64);
                $table->string('source')->nullable();
                $table->unsignedInteger('component_count')->default(0);
                $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
                $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
                $table->foreignId('imported_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();

                $table->index('document_sha256');
                $table->index('application_id');
                $table->index('asset_id');
            });
        }

        if (! Schema::hasTable('software_components')) {
            Schema::create('software_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sbom_document_id')->constrained('sbom_documents')->cascadeOnDelete();
                $table->string('component_key', 512);
                $table->string('purl', 512)->nullable();
                $table->string('name');
                $table->string('version')->nullable();
                $table->string('supplier')->nullable();
                $table->string('license')->nullable();
                $table->string('ecosystem', 64)->nullable();
                $table->string('scope', 32)->nullable();
                $table->string('type', 32)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['sbom_document_id', 'component_key']);
                $table->index('name');
                $table->index('ecosystem');
            });
        }

        if (! Schema::hasTable('software_vulnerabilities')) {
            Schema::create('software_vulnerabilities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('software_component_id')->constrained('software_components')->cascadeOnDelete();
                $table->foreignId('finding_id')->nullable()->constrained('findings')->nullOnDelete();
                $table->string('osv_id', 64);
                $table->json('aliases')->nullable();
                $table->text('summary')->nullable();
                $table->text('details')->nullable();
                $table->string('severity', 16)->default('medium');
                $table->text('affected')->nullable();
                $table->string('fixed_version')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('modified_at')->nullable();
                $table->string('source_url')->nullable();
                $table->string('status', 16)->default('open');
                $table->timestamp('first_seen_at');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['software_component_id', 'osv_id']);
                $table->index('osv_id');
                $table->index('severity');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('software_vulnerabilities');
        Schema::dropIfExists('software_components');
        Schema::dropIfExists('sbom_documents');
    }
};
