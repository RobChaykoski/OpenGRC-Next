<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Register of service accounts, tokens and other machine credentials.
 *
 * The secret is never stored here. `secret_reference` points at the secret store;
 * the register holds ownership, scope, rotation, expiry and review state.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('non_human_identities')) {
            Schema::create('non_human_identities', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 32);
                $table->string('system');
                $table->string('environment', 32)->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('purpose')->nullable();
                $table->text('scope')->nullable();
                $table->string('secret_reference')->nullable();
                $table->unsignedInteger('rotation_days')->nullable();
                $table->date('last_rotated_at')->nullable();
                $table->date('next_rotation_at')->nullable();
                $table->date('last_used_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->string('status', 32)->default('active');
                $table->date('reviewed_at')->nullable();
                $table->date('next_review_at')->nullable();
                $table->date('revoked_at')->nullable();
                $table->text('revocation_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['system', 'name']);
                $table->index('type');
                $table->index('status');
                $table->index('next_rotation_at');
                $table->index('expires_at');
                $table->index('next_review_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('non_human_identities');
    }
};
