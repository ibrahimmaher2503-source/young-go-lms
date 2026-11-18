<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * T-P9-05: the admin role edits notification templates, which needs `system.settings.manage`. Fresh seeds get it from
 * config/permissions.php; this grants it on databases that were seeded earlier. Site identity and the payment payee stay
 * behind the separate `system.site_settings.manage`, which only super_admin holds.
 */
return new class extends Migration
{
    private const array GRANTS = [
        'admin' => ['system.settings.manage'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $guard = (string) config('permissions.guard', 'web');

        foreach (self::GRANTS as $role => $names) {
            $roleId = DB::table('roles')->where('name', $role)->where('guard_name', $guard)->value('id');
            if ($roleId === null) {
                continue;
            }

            foreach ($names as $name) {
                DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
                $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id');
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Grants are not revoked: an operator may have assigned the permission independently.
    }
};
