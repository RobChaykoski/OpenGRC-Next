<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of integrity verification runs (evidence and audit hash chains).
 *
 * The chains themselves stay append-only. This table records that a verifier
 * looked, when, and what it found, so a stopped schedule is visible rather than
 * silent. It is operational history, not evidence: rows are pruned after the
 * retention window in config/integrity.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integrity_checks')) {
            return;
        }

        Schema::create('integrity_checks', function (Blueprint $table) {
            $table->id();
            $table->string('check', 32);
            $table->string('status', 16);
            $table->string('summary');
            $table->json('detail')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['check', 'checked_at']);
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_checks');
    }
};
