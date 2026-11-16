<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final class AuthenticateAccount
{
    public function handle(string $login, #[SensitiveParameter] string $password): void
    {
        $column = str_contains($login, '@') ? 'email' : 'username';
        if (! Auth::guard('web')->attempt([$column => $login, 'password' => $password, 'status' => 'active'])) {
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        $user = Auth::guard('web')->user();
        if ($user instanceof User) {
            $user->setAttribute('last_login_at', now())->save();
        }
    }
}
