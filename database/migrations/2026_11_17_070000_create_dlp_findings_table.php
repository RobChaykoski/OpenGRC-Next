<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DLP findings are metadata, never the content that triggered them. The hash
 * lets an investigator correlate a finding with a stored record without copying
 * the sensitive value into this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlp_findings')) {
            return;
        }

        Schema::create('dlp_findings', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->nullableMorphs('context');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('severity', 16);
            $table->json('match_types');
            $table->string('action', 16);
            $table->char('content_sha256', 64);
            $table->timestamps();

            $table->index(['source', 'created_at']);
            $table->index(['severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dlp_findings');
    }
};
