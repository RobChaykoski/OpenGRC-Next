<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DLP policies as governance records (ADR 0053).
 *
 * The scanning engine owns detectors and enforcement. This table records the
 * intent a human approved: which channels and data classes are covered, what
 * happens on a match, who owns the policy and when it was last reviewed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlp_policies')) {
            return;
        }

        Schema::create('dlp_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('channels')->nullable();
            $table->json('classifications')->nullable();
            $table->json('identifier_types')->nullable();
            $table->string('action', 16)->default('alert');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('review_due_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'review_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dlp_policies');
    }
};
