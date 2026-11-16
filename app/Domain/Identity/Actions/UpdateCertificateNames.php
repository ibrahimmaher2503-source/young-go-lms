<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateCertificateNames
{
    /** @param array<string, mixed> $names */
    public function handle(User $user, array $names): void
    {
        DB::transaction(function () use ($user, $names): void {
            $profile = User::query()->lockForUpdate()->findOrFail($user->getKey());
            if ($profile->getAttribute('certificate_names_locked_at') !== null) {
                throw ValidationException::withMessages(['certificate_name_en' => __('profile.certificate_names_locked')]);
            }

            foreach (Arr::only($names, ['certificate_name_ar', 'certificate_name_en']) as $key => $value) {
                $profile->setAttribute($key, $value);
            }

            $profile->save();
        });
    }
}
