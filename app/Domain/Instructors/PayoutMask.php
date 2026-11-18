<?php

declare(strict_types=1);

namespace App\Domain\Instructors;

use App\Domain\Instructors\Models\PayoutProfile;

/** Masked view of a payout profile for lists and the owner's own page: only the last four characters of an identifier survive. */
final class PayoutMask
{
    public static function value(?string $value): string
    {
        $value = trim((string) $value);
        $length = mb_strlen($value);
        if ($length === 0) {
            return '';
        }

        return str_repeat('*', max(4, $length - 4)).mb_substr($value, -min(4, $length));
    }

    /** The one identifier that says where money goes (account/IBAN, wallet number or Instapay address), masked. */
    public static function identifier(PayoutProfile $profile): string
    {
        $details = $profile->details;

        return self::value(match ($profile->method) {
            PayoutProfile::WALLET => $details['wallet_number'] ?? '',
            PayoutProfile::INSTAPAY => $details['instapay_address'] ?? '',
            default => $details['account_number'] ?? '',
        });
    }
}
