<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuthorizeSuperAdminBypass
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, string $ability, ?Model $target): bool
    {
        return $this->audit->record(
            'permission.super_admin_bypass',
            $target ?? $actor,
            (int) $actor->getKey(),
            context: ['ability' => $ability],
        ) !== null;
    }
}
