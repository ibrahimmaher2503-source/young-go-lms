<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisterAccount
{
    /**
     * @param  array{email:string, username?:?string, password:string, name_ar?:?string, name_en?:?string, birth_year?:?int, birth_month?:?int, account_type?:string}  $input
     */
    public function handle(array $input, string $termsVersion, string $privacyVersion): User
    {
        try {
            $user = DB::transaction(function () use ($input, $termsVersion, $privacyVersion): User {
                // Public sign-up can only create a learner or a parent; staff roles are never self-assigned.
                $role = ($input['account_type'] ?? 'learner') === 'parent' ? 'parent' : 'student';
                unset($input['account_type']);
                $displayName = $input['name_ar'] ?? $input['name_en'] ?? $input['email'];
                $user = new User;
                $user->forceFill([
                    ...$input,
                    'name' => $displayName,
                    'display_name' => $displayName,
                    'locale' => App::currentLocale(),
                ])->save();

                $user->assignRole($role);

                $grantedAt = now();
                DB::table('consents')->insert([
                    ['user_id' => $user->getKey(), 'type' => 'terms', 'version' => $termsVersion, 'granted_at' => $grantedAt],
                    ['user_id' => $user->getKey(), 'type' => 'privacy', 'version' => $privacyVersion, 'granted_at' => $grantedAt],
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $errors = [];
            foreach (['email', 'username'] as $field) {
                if (isset($input[$field]) && User::where($field, $input[$field])->exists()) {
                    $errors[$field] = __('validation.unique', ['attribute' => __("validation.attributes.{$field}")]);
                }
            }

            throw $errors === [] ? $exception : ValidationException::withMessages($errors);
        }

        event(new Registered($user));

        return $user;
    }
}
