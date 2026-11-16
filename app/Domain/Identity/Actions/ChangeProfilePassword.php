<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChangeProfilePassword
{
    public function handle(User $user, string $currentPassword, string $password): void
    {
        DB::transaction(function () use ($user, $currentPassword, $password): void {
            $profile = User::query()->lockForUpdate()->findOrFail($user->getKey());
            if (! Hash::check($currentPassword, $profile->getAuthPassword())) {
                throw ValidationException::withMessages(['current_password' => __('profile.current_password')]);
            }

            $profile->setAttribute('password', Hash::make($password));
            $profile->setRememberToken(Str::random(60));
            $profile->save();
        });
    }
}
