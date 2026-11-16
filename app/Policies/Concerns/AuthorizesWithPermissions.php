<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesWithPermissions
{
    protected function allows(User $user, string $permission): bool
    {
        return $user->can($permission);
    }
}
