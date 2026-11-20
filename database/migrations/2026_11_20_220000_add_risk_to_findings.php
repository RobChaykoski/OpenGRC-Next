<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a finding to the risk it contributes to.
 *
 * The link is optional. A finding can exist before a risk is raised, and a risk
 * can exist without a finding. The link records the treatment relationship; it
 * does not close either record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('findings') && ! Schema::hasColumn('findings', 'risk_id')) {
            Schema::table('findings', function (Blueprint $table) {
                $table->foreignId('risk_id')->nullable()->after('control_id')
                    ->constrained('risks')->nullOnDelete();

                $table->index('risk_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('findings') && Schema::hasColumn('findings', 'risk_id')) {
            Schema::table('findings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('risk_id');
            });
        }
    }
};
