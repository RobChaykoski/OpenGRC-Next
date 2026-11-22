<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client streaming credentials and rejection counters (ADR 0054).
 *
 * A stream is a named producer scope inside one client. The secret is the HMAC
 * key the producer signs each request with; it is encrypted at rest, shown once
 * when created, and never returned by the API.
 *
 * Rejections are counted, never stored: the body may be the sensitive thing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evidence_streams')) {
            return;
        }

        Schema::create('evidence_streams', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 64);
            $table->string('name', 64);
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->string('last_cursor', 191)->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->string('last_rejection_reason', 255)->nullable();
            $table->timestamp('last_rejection_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'name']);
            $table->index(['client_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_streams');
    }
};
