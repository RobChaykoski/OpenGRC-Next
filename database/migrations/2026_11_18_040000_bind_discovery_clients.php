<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bind machine credentials and ingested evidence to a discovery client.
     *
     * A token without a client_id cannot write evidence, and an external_id is
     * unique per client, so a replayed snapshot can never create a second row.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'client_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->string('client_id', 64)->nullable()->after('abilities')->index();
            });
        }

        if (! Schema::hasColumn('evidence_records', 'client_id')) {
            Schema::table('evidence_records', function (Blueprint $table) {
                $table->string('client_id', 64)->nullable()->after('external_id')->index();
                $table->unique(['client_id', 'external_id'], 'evidence_records_client_external_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evidence_records', 'client_id')) {
            Schema::table('evidence_records', function (Blueprint $table) {
                $table->dropUnique('evidence_records_client_external_unique');
                $table->dropColumn('client_id');
            });
        }

        if (Schema::hasColumn('personal_access_tokens', 'client_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropColumn('client_id');
            });
        }
    }
};
