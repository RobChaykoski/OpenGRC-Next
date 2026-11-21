<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scoped, read-only auditor portal links (ADR 0045).
 *
 * A grant belongs to exactly one audit. Only the SHA-256 of the link token is
 * stored, so a database reader cannot reconstruct a working link. Views are kept
 * separately because the portal is an evidence surface: who looked, when, and
 * from where has to be attributable after the audit closes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_portal_grants')) {
            return;
        }

        Schema::create('audit_portal_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
            $table->string('label');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamps();

            $table->index(['audit_id', 'expires_at']);
        });

        Schema::create('audit_portal_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_portal_grant_id')
                ->constrained('audit_portal_grants')
                ->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('viewed_at');

            $table->index(['audit_portal_grant_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_portal_views');
        Schema::dropIfExists('audit_portal_grants');
    }
};
