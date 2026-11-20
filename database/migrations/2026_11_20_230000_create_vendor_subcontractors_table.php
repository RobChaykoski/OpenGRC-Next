<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fourth-party (subcontractor) records for a vendor.
 *
 * A subcontractor is recorded here rather than as another Vendor when the
 * programme does not manage it directly. It is a register entry, not a graph.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_subcontractors')) {
            return;
        }

        Schema::create('vendor_subcontractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name');
            $table->string('service')->nullable();
            $table->string('country', 64)->nullable();
            $table->boolean('accesses_personal_data')->default(false);
            $table->string('risk_rating', 32)->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'name']);
            $table->index('last_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_subcontractors');
    }
};
