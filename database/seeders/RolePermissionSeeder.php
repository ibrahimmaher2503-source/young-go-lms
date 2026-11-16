<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = (string) config('permissions.guard', 'web');
        $permissionNames = [];

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (config('permissions.permissions', []) as $definition) {
            $name = $definition['name'];
            $permissionNames[] = $name;
            Permission::findOrCreate($name, $guard);
        }

        foreach (config('permissions.roles', []) as $name => $grants) {
            $role = Role::findOrCreate($name, $guard);

            if (! $role->wasRecentlyCreated) {
                continue;
            }

            $names = $grants === ['*'] ? $permissionNames : $grants;
            $permissions = Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', $names)
                ->get();

            if ($permissions->isNotEmpty()) {
                $role->givePermissionTo($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
