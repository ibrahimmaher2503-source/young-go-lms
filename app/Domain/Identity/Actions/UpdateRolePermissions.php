<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class UpdateRolePermissions
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<string> $names */
    public function handle(User $actor, Role $role, array $names): void
    {
        DB::transaction(function () use ($actor, $role, $names): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $role = Role::query()->lockForUpdate()->findOrFail($role->getKey());
            if ($actor->getAttribute('status') !== 'active' || ! $actor->hasRole('super_admin')
                || $role->guard_name !== 'web' || $role->name === 'super_admin') {
                throw new AuthorizationException;
            }

            $names = array_values(array_unique($names));
            sort($names);
            $permissions = Permission::query()->where('guard_name', 'web')->whereIn('name', $names)->get();
            if ($permissions->count() !== count($names)) {
                throw ValidationException::withMessages(['permissions' => __('validation.in', ['attribute' => __('staff.permissions')])]);
            }

            $before = $role->permissions->pluck('name')->sort()->values()->all();
            if ($before === $names) {
                return;
            }

            $role->syncPermissions($permissions);
            if ($this->audit->record('role.permissions_changed', $role, $actor->id, ['permissions' => $before], ['permissions' => $names]) === null) {
                throw new RuntimeException('Unable to record permission change audit.');
            }
        });
    }
}
