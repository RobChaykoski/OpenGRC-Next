<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable document templates (NDAs, scopes, procedures).
 *
 * The original DOCX stays on the private disk. The extracted body is encrypted
 * at rest so a database copy does not disclose the template text.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_templates')) {
            return;
        }

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->nullable()->unique();
            $table->string('type', 32);
            $table->string('version', 32)->default('1.0');
            $table->string('status', 32)->default('draft');
            $table->string('classification', 32)->default('internal');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('policies')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->longText('body_text')->nullable();
            $table->json('placeholders')->nullable();
            $table->json('outline')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retention_until')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('classification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
