<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-file hashes for a registered model artifact (ADR 0059).
 *
 * A model is rarely one file. The tokenizer, config and chat template change
 * behaviour without changing a weight, so each file has its own record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('model_artifact_files')) {
            return;
        }

        Schema::create('model_artifact_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_artifact_id')->constrained('model_artifacts')->cascadeOnDelete();
            $table->string('path', 1024);
            $table->string('kind', 24)->default('other');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->timestamps();

            $table->unique(['model_artifact_id', 'path'], 'model_artifact_files_path_unique');
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_artifact_files');
    }
};
