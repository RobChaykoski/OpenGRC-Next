<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI model artifacts as a register (ADR 0059).
 *
 * The row holds identity, format, provenance, hashes and the approval decision.
 * It never holds tensor data, tokenizer vocabulary or a prompt template body.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('model_artifacts')) {
            return;
        }

        Schema::create('model_artifacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source', 1024)->nullable();
            $table->string('revision', 191)->nullable();
            $table->string('format', 24)->default('unknown');
            $table->string('license', 64)->nullable();
            $table->string('architecture', 64)->nullable();
            $table->string('quantization', 32)->nullable();
            $table->unsignedInteger('context_length')->nullable();
            $table->unsignedInteger('tensor_count')->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);

            // SHA-256 of the sorted path:hash list. Identifies the artifact
            // contents without storing them.
            $table->string('content_hash', 64)->nullable();
            $table->string('tokenizer_hash', 64)->nullable();
            $table->string('chat_template_hash', 64)->nullable();

            $table->string('conversion_tool', 64)->nullable();
            $table->string('conversion_version', 64)->nullable();
            $table->timestamp('conversion_recorded_at')->nullable();
            $table->string('provenance_status', 16)->default('unverified');
            $table->timestamp('provenance_verified_at')->nullable();

            $table->string('status', 16)->default('proposed');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->string('scan_tool', 64)->nullable();
            $table->string('scan_tool_version', 64)->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('format');
            $table->index(['status', 'created_at']);
            $table->index('content_hash');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_artifacts');
    }
};
