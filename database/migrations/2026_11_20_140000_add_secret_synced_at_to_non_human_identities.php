<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('non_human_identities') && ! Schema::hasColumn('non_human_identities', 'secret_synced_at')) {
            Schema::table('non_human_identities', function (Blueprint $table) {
                $table->timestamp('secret_synced_at')->nullable()->after('revocation_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('non_human_identities') && Schema::hasColumn('non_human_identities', 'secret_synced_at')) {
            Schema::table('non_human_identities', function (Blueprint $table) {
                $table->dropColumn('secret_synced_at');
            });
        }
    }
};
