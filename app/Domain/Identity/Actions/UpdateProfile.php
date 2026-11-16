<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateProfile
{
    /** @param array<string, mixed> $attributes */
    public function handle(User $user, array $attributes): void
    {
        DB::transaction(function () use ($user, $attributes): void {
            $profile = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $names = Arr::only($attributes, ['name_ar', 'name_en']);
            if ($names !== [] && blank(array_key_exists('name_ar', $names) ? $names['name_ar'] : $profile->getAttribute('name_ar'))
                && blank(array_key_exists('name_en', $names) ? $names['name_en'] : $profile->getAttribute('name_en'))) {
                throw ValidationException::withMessages(['name_en' => __('profile.name_required')]);
            }

            foreach (Arr::only($attributes, ['name_ar', 'name_en', 'display_name', 'locale', 'timezone', 'notification_preferences']) as $key => $value) {
                $profile->setAttribute($key, $key === 'notification_preferences' ? json_encode($value, JSON_THROW_ON_ERROR) : $value);
            }

            $profile->save();
        });
    }
}
