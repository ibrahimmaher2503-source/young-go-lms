<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

use App\Models\User;

/**
 * AI spec 8.1: phone numbers, emails, national ids, simple street addresses and the learner's own name are replaced with
 * placeholders before the question is stored or sent anywhere. Pattern based, so it is a best-effort floor.
 */
final class PiiMasker
{
    private const string DIGIT = '[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}]';

    private const array ASCII_DIGITS = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'];

    public function mask(string $text, ?User $learner = null): MaskedText
    {
        $found = [];
        $count = static function (string $kind) use (&$found): void {
            $found[$kind] = ($found[$kind] ?? 0) + 1;
        };

        $text = preg_replace_callback('/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.\p{L}{2,}/u', static function () use ($count): string {
            $count('email');

            return '[email]';
        }, $text) ?? $text;

        $digit = self::DIGIT;
        $text = preg_replace_callback('/(?<!\p{N})\+?'.$digit.'(?:[\s().\-]{0,2}'.$digit.'){7,}(?!\p{N})/u', static function (array $match) use ($count): string {
            $raw = $match[0];
            $ascii = strtr($raw, self::ASCII_DIGITS);
            // A plain date is not a phone number.
            if (preg_match('/^\d{4}[-.\/]\d{1,2}[-.\/]\d{1,2}$|^\d{1,2}[-.\/]\d{1,2}[-.\/]\d{4}$/', trim($ascii)) === 1) {
                return $raw;
            }

            $digits = preg_replace('/\D/', '', $ascii) ?? '';
            $plain = preg_match('/^\d+$/', $ascii) === 1;
            $isId = strlen($digits) === 14 || ($plain && strlen($digits) === 10 && in_array($digits[0], ['1', '2'], true));
            $count($isId ? 'id' : 'phone');

            return $isId ? '[id]' : '[phone]';
        }, $text) ?? $text;

        $text = preg_replace_callback('/\b\d{1,5}\s+(?:[\p{L}.]+\s+){1,3}(?:street|st|road|rd|avenue|ave|lane|ln)\b\.?|(?:شارع|عمارة)\s+[\p{L}\p{N}]+(?:\s+[\p{L}\p{N}]+)?/iu', static function () use ($count): string {
            $count('address');

            return '[address]';
        }, $text) ?? $text;

        $tokens = $learner === null ? [] : array_values(array_unique(array_filter(
            preg_split('/\s+/u', trim((string) $learner->getAttribute('name'))) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 3,
        )));
        if ($tokens !== []) {
            $pattern = '/(?<![\p{L}\p{N}])(?:'.implode('|', array_map(static fn (string $token): string => preg_quote($token, '/'), $tokens)).')(?![\p{L}\p{N}])/iu';
            $text = preg_replace_callback($pattern, static function () use ($count): string {
                $count('name');

                return '[name]';
            }, $text) ?? $text;
        }

        return new MaskedText($text, $found);
    }
}
