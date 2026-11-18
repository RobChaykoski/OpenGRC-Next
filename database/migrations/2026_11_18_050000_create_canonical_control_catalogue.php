<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the unified control catalogue authored in config/framework_scoping.php.
 *
 * The config stays the source of truth for which outcomes exist and which
 * frameworks require them; these tables are the join target for internal
 * controls, evidence, policies, and the matrix page. `opengrc:catalog:sync`
 * seeds them idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('canonical_frameworks')) {
            Schema::create('canonical_frameworks', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->string('category', 64)->nullable();
                $table->unsignedTinyInteger('priority')->default(3);
                $table->json('regions')->nullable();
                $table->json('industries')->nullable();
                $table->json('labels')->nullable();
                $table->json('drivers')->nullable();
                $table->text('rationale')->nullable();
                $table->timestamps();

                $table->index(['category', 'priority']);
            });
        }

        if (! Schema::hasTable('canonical_controls')) {
            Schema::create('canonical_controls', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->string('category', 64)->nullable();
                $table->string('risk_domain', 64)->nullable();
                $table->unsignedTinyInteger('priority')->default(3);
                $table->text('evidence_expectation')->nullable();
                $table->string('automation_level', 16)->nullable();
                $table->string('frequency', 32)->nullable();
                $table->json('sources')->nullable();
                $table->timestamps();

                $table->index(['category', 'priority']);
                $table->index('risk_domain');
            });
        }

        if (! Schema::hasTable('canonical_control_framework')) {
            Schema::create('canonical_control_framework', function (Blueprint $table) {
                $table->id();
                $table->foreignId('canonical_control_id')->constrained('canonical_controls')->cascadeOnDelete();
                $table->foreignId('canonical_framework_id')->constrained('canonical_frameworks')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['canonical_control_id', 'canonical_framework_id'], 'canonical_control_framework_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_control_framework');
        Schema::dropIfExists('canonical_controls');
        Schema::dropIfExists('canonical_frameworks');
    }
};
