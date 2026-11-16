<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Closure;
use Illuminate\Validation\Rules\Password;

final class NewPassword
{
    /** @return list<string|Password|Closure> */
    public static function rules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8), self::byteLimit(...)];
    }

    public static function byteLimit(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > 72) {
            $fail('auth.password_too_long')->translate();
        }
    }
}
