<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A control owner's periodic confirmation that an implementation is still true.
 *
 * This is not an access review and not a policy acknowledgement. It records a
 * request, the owner's response and the statement they gave, with a due date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('control_attestations')) {
            return;
        }

        Schema::create('control_attestations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('implementation_id')->constrained('implementations')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->date('due_at');
            $table->string('status', 32)->default('pending');
            $table->text('request_note')->nullable();
            $table->foreignId('attested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attested_at')->nullable();
            $table->text('statement')->nullable();
            $table->timestamps();

            $table->index(['implementation_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_attestations');
    }
};
