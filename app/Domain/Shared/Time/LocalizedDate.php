<?php

declare(strict_types=1);

namespace App\Domain\Shared\Time;

use App\Domain\Shared\Markets\Market;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use RuntimeException;

final class LocalizedDate
{
    public static function format(
        DateTimeInterface $instant,
        Market $market,
        string $locale,
        string $pattern = 'd MMMM yyyy HH:mm',
        ?string $timezone = null,
    ): string {
        $formatter = new IntlDateFormatter(
            "{$locale}-{$market->value}-u-nu-latn",
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            new DateTimeZone($timezone ?? $market->timezone()),
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );
        $formatted = $formatter->format($instant->getTimestamp());
        if ($formatted === false) {
            throw new RuntimeException($formatter->getErrorMessage());
        }

        return $formatted;
    }
}
