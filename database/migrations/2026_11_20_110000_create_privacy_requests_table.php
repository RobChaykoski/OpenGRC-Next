<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy requests (DSARs) are not audit evidence requests.
 *
 * A privacy request has a statutory response clock, a request type, a
 * verification step and a denial or extension decision. It is kept separate from
 * the audit-oriented data_requests table so neither vocabulary distorts the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('privacy_requests')) {
            Schema::create('privacy_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('regime', 32);
                $table->string('request_type', 32);
                $table->string('status', 32)->default('received');
                $table->string('requester_name')->nullable();
                $table->string('contact_reference')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('received_at');
                $table->date('due_at')->nullable();
                $table->date('extended_at')->nullable();
                $table->text('extension_reason')->nullable();
                $table->boolean('identity_verified')->default(false);
                $table->string('verification_method')->nullable();
                $table->date('verified_at')->nullable();
                $table->text('denial_reason')->nullable();
                $table->text('response_summary')->nullable();
                $table->date('completed_at')->nullable();
                $table->string('evidence_reference')->nullable();
                $table->char('evidence_sha256', 64)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('regime');
                $table->index('request_type');
                $table->index('status');
                $table->index('due_at');
                $table->index('extended_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
