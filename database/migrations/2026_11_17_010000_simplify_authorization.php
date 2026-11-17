<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the Spatie role/permission model with a single role column.
     *
     * Backfill runs before the tables are dropped, so existing administrators
     * keep their access. Everyone else becomes a viewer; editors are granted
     * deliberately after the upgrade.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)->default('viewer')->after('password')->index();
            });
        }

        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $adminRoleIds = DB::table('roles')
                ->whereIn('name', ['Super Admin', 'Security Admin'])
                ->pluck('id');

            $adminUserIds = DB::table('model_has_roles')
                ->whereIn('role_id', $adminRoleIds)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id');

            if ($adminUserIds->isNotEmpty()) {
                DB::table('users')->whereIn('id', $adminUserIds)->update(['role' => 'admin']);
            }
        }

        foreach ([
            'role_has_permissions',
            'model_has_permissions',
            'model_has_roles',
            'permissions',
            'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Irreversible by design.
     *
     * Recreating the permission tables without their contents would be worse
     * than leaving the role column in place. Restore from a backup if you need
     * to roll back before the upgrade.
     */
    public function down(): void
    {
        //
    }
};
