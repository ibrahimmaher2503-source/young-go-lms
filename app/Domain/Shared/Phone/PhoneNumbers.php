<?php

declare(strict_types=1);

namespace App\Domain\Shared\Phone;

use InvalidArgumentException;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

final class PhoneNumbers
{
    private const REGIONS = ['EG', 'SA'];

    public static function normalize(string $number, string $region = 'EG'): string
    {
        self::checkRegion($region);
        $phone = new PhoneNumber($number, $region);

        if (! $phone->isValid() || ! $phone->isOfCountry($region)) {
            throw new InvalidArgumentException('Invalid phone number for the selected region.');
        }

        return $phone->formatE164();
    }

    public static function display(string $canonical): string
    {
        return (new PhoneNumber($canonical))->formatNational();
    }

    public static function rule(string $region = 'EG'): Phone
    {
        self::checkRegion($region);

        return (new Phone)->country($region);
    }

    private static function checkRegion(string $region): void
    {
        if (! in_array($region, self::REGIONS, true)) {
            throw new InvalidArgumentException('Unsupported phone region.');
        }
    }
}
