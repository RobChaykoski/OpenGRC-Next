<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy acknowledgements are versioned attestations.
 *
 * A row records that a user acknowledged a specific policy revision. When the
 * policy content changes, the revision changes and the old acknowledgement is
 * stale; it is not overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('policy_acknowledgements')) {
            Schema::create('policy_acknowledgements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('policy_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->char('policy_revision', 64);
                $table->timestamp('policy_updated_at')->nullable();
                $table->timestamp('acknowledged_at');
                $table->string('method', 16)->default('web');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['policy_id', 'user_id', 'policy_revision']);
                $table->index('user_id');
                $table->index('acknowledged_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acknowledgements');
    }
};
