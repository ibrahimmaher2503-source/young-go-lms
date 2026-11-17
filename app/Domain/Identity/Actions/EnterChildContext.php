<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\ActingLearner;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class EnterChildContext
{
    public function __construct(private readonly AuditLogger $audit, private readonly ActingLearner $acting) {}

    /** @param int|null $passwordConfirmedAt Unix time of the parent's latest password confirmation. */
    public function handle(User $parent, User $child, ?int $passwordConfirmedAt): void
    {
        if ($passwordConfirmedAt === null || time() - $passwordConfirmedAt > Config::integer('auth.password_timeout')) {
            throw new AuthorizationException(__('guardian.password_confirmation_required'));
        }

        Gate::forUser($parent)->authorize('guardian.child.access', $child);

        $this->acting->enter($child);

        if ($this->audit->record('guardian.context_entered', $child, (int) $parent->getKey()) === null) {
            $this->acting->exit();
            throw new RuntimeException('Unable to record context switch audit.');
        }
    }
}
