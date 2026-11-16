<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Verified;

final class VerifyAccountEmail
{
    public function handle(User $user): void
    {
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }
    }
}
