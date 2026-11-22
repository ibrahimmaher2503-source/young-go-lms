<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CreateManagedAccount
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, string $name, string $email): void
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('user.manage')) {
            throw new AuthorizationException;
        }

        $name = trim($name);
        $email = mb_strtolower(trim($email));
        Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', 'email', 'unique:users,email'],
        ], attributes: ['name' => __('staff.name'), 'email' => __('staff.email')])->validate();

        try {
            DB::transaction(function () use ($actor, $name, $email): void {
                // Only the mailbox owner can set a known password through the existing recovery flow.
                $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Str::random(48)]);

                if ($this->audit->record('user.created', $user, (int) $actor->getKey()) === null) {
                    throw new RuntimeException('Unable to record account creation audit.');
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! User::query()->where('email', $email)->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => __('staff.email')])]);
        }
    }
}
