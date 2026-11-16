<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;

final class AssignStaffRoles
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<string> $names */
    public function handle(User $actor, User $target, array $names): void
    {
        DB::transaction(function () use ($actor, $target, $names): void {
            $lockedUsers = User::query()->whereKey([$actor->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $lockedUsers->get($actor->id);
            $target = $lockedUsers->get($target->id);

            if ($actor === null || $target === null
                || $actor->getAttribute('status') !== 'active' || ! $actor->hasRole('super_admin')
                || $target->hasRole('super_admin') || in_array('super_admin', $names, true)) {
                throw new AuthorizationException;
            }

            $names = array_values(array_unique($names));
            sort($names);
            $roles = Role::query()->where('guard_name', 'web')->whereIn('name', $names)->get();
            if ($roles->count() !== count($names)) {
                throw ValidationException::withMessages(['roles' => __('validation.in', ['attribute' => __('staff.roles')])]);
            }

            $before = $target->getRoleNames()->sort()->values()->all();
            if ($before === $names) {
                return;
            }

            $target->syncRoles($roles);
            if ($this->audit->record('user.roles_changed', $target, $actor->id, ['roles' => $before], ['roles' => $names]) === null) {
                throw new RuntimeException('Unable to record role assignment audit.');
            }
        });
    }
}
