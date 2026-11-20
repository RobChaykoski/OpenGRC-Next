<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only mirror of the OpenDiscoveryEngine console.
 *
 * These tables are a local copy of what the engine already knows: scans and
 * their findings, keyed by the engine's tenant and identifiers. Nothing here is
 * pushed back, and the remote row is the source of truth. A replayed sync
 * updates the same row instead of creating a second one, so `(tenant, remote_id)`
 * is unique on both tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discovery_scans')) {
            Schema::create('discovery_scans', function (Blueprint $table) {
                $table->id();
                $table->string('tenant', 191)->nullable();
                $table->string('remote_id', 191);
                $table->string('asset_id', 191)->nullable();
                $table->string('asset_name')->nullable();
                $table->string('profile_id', 191)->nullable();
                $table->string('profile_name')->nullable();
                $table->string('status', 32)->nullable();
                $table->unsignedInteger('files_seen')->default(0);
                $table->unsignedInteger('files_scanned')->default(0);
                $table->unsignedInteger('findings_count')->default(0);
                $table->text('error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('remote_created_at')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant', 'remote_id']);
                $table->index('asset_id');
                $table->index('status');
                $table->index('finished_at');
            });
        }

        if (! Schema::hasTable('discovery_findings')) {
            Schema::create('discovery_findings', function (Blueprint $table) {
                $table->id();
                $table->string('tenant', 191)->nullable();
                $table->string('remote_id', 191);
                $table->foreignId('discovery_scan_id')->nullable()->constrained('discovery_scans')->nullOnDelete();
                $table->string('remote_scan_id', 191)->nullable();
                $table->string('asset_id', 191)->nullable();
                $table->string('asset_name')->nullable();
                $table->string('detector_key', 191)->nullable();
                $table->string('category', 64)->nullable();
                $table->string('severity', 16)->nullable();
                $table->string('classification', 64)->nullable();
                $table->text('path')->nullable();
                $table->text('match_preview')->nullable();
                $table->char('match_sha256', 64)->nullable();
                $table->float('confidence')->nullable();
                $table->string('status', 32)->nullable();
                $table->timestamp('remote_created_at')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant', 'remote_id']);
                $table->index('remote_scan_id');
                $table->index('asset_id');
                $table->index('detector_key');
                $table->index(['severity', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_findings');
        Schema::dropIfExists('discovery_scans');
    }
};
