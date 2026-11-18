<?php

declare(strict_types=1);

namespace App\Domain\Support;

/** Field masking for Learner 360. Masked values keep just enough shape for staff to confirm an identity with the caller. */
final class Learner360Mask
{
    public static function email(?string $email): ?string
    {
        if ($email === null || $email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $tld = str_contains($domain, '.') ? substr($domain, (int) strrpos($domain, '.')) : '';

        return mb_substr($local, 0, 1).'***@'.mb_substr($domain, 0, 1).'***'.$tld;
    }

    public static function phone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? null : '••••'.substr($digits, -3);
    }
}
