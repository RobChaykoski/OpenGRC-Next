<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Risk acceptance as a dated decision.
 *
 * A mitigation strategy of "Accept" says what was chosen; these fields record
 * who accepted the residual risk, when, for how long and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('risks') && ! Schema::hasColumn('risks', 'acceptance_status')) {
            Schema::table('risks', function (Blueprint $table) {
                $table->string('acceptance_status', 32)->default('none');
                $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('accepted_at')->nullable();
                $table->date('acceptance_expires_at')->nullable();
                $table->text('acceptance_rationale')->nullable();

                $table->index('acceptance_status');
                $table->index('acceptance_expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('risks') && Schema::hasColumn('risks', 'acceptance_status')) {
            Schema::table('risks', function (Blueprint $table) {
                $table->dropIndex(['acceptance_status']);
                $table->dropIndex(['acceptance_expires_at']);
                $table->dropConstrainedForeignId('accepted_by');
                $table->dropColumn([
                    'acceptance_status',
                    'accepted_at',
                    'acceptance_expires_at',
                    'acceptance_rationale',
                ]);
            });
        }
    }
};
