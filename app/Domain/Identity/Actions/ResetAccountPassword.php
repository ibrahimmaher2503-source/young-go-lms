<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final class ResetAccountPassword
{
    public function handle(string $email, #[SensitiveParameter] string $token, #[SensitiveParameter] string $password): void
    {
        $status = DB::transaction(function () use ($email, $token, $password): string {
            // Serialize broker token validation, password update and token deletion per account.
            if (User::where('email', $email)->where('status', 'active')->lockForUpdate()->first() === null) {
                return Password::INVALID_USER;
            }

            return Password::reset(
                ['email' => $email, 'token' => $token, 'password' => $password, 'status' => 'active'],
                static function (User $user, #[SensitiveParameter] string $password): void {
                    $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                    event(new PasswordReset($user));
                },
            );
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('auth.reset_invalid')]);
        }
    }
}
