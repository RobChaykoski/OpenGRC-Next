<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide IP and email-domain allow/deny rules (ADR 0047).
 *
 * Deny wins. If a scope has one or more allow rules for a dimension (IP or
 * email domain), a request must match that dimension. The two dimensions are
 * independent: an email allowlist does not whitelist an IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('access_rules')) {
            return;
        }

        Schema::create('access_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 24);
            $table->string('effect', 8);
            $table->string('scope', 24);
            $table->string('value', 191);
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'effect', 'scope', 'value']);
            $table->index(['scope', 'is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_rules');
    }
};
