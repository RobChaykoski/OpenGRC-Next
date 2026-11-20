<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access review campaigns and decisions.
 *
 * A campaign snapshots the accounts and their roles at the start. The reviewer
 * attests or revokes each account; the decision is evidence, not an automatic
 * disable in the identity provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('access_reviews')) {
            Schema::create('access_reviews', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('status', 32)->default('draft');
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('due_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index('due_at');
            });
        }

        if (! Schema::hasTable('access_review_decisions')) {
            Schema::create('access_review_decisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('access_review_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role_at_review', 20);
                $table->boolean('mfa_at_review')->default(false);
                $table->string('decision', 32)->default('pending');
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['access_review_id', 'user_id']);
                $table->index('decision');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('access_review_decisions');
        Schema::dropIfExists('access_reviews');
    }
};
