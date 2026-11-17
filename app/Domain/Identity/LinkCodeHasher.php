<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use Illuminate\Support\Facades\Config;

final class LinkCodeHasher
{
    /** Unambiguous characters: no 0/O, 1/I/L. */
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const int LENGTH = 8;

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', strtoupper(trim($code)), Config::string('app.key'));
    }
}
