<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The data side of access governance (ADR 0049).
 *
 * A protected resource is a dataset, table, column set, record scope or API
 * whose classification drives an access decision. The row is a governance
 * record, not a copy of the data: no sample values and no row content.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('protected_resources')) {
            return;
        }

        Schema::create('protected_resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('resource_type', 24)->default('dataset');
            $table->string('external_urn', 191)->nullable()->unique();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('tier', 16)->default('confidential');
            $table->string('classification_code', 64)->nullable();
            $table->string('consent_directive', 24)->default('none');
            $table->string('jurisdiction', 64)->nullable();
            $table->boolean('is_crown_jewel')->default(false);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('classification_source', 24)->default('manual');
            $table->string('classification_version', 32)->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tier', 'is_crown_jewel']);
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protected_resources');
    }
};
