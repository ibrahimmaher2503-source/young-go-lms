<?php

declare(strict_types=1);

namespace App\Domain\Shared\Money;

use App\Domain\Shared\Markets\Market;
use Brick\Money\CurrencyDisplay;
use InvalidArgumentException;

final class PriceFormatter
{
    public function format(Money $money, Market $market, string $locale): string
    {
        if ($money->value->getCurrency()->getCurrencyCode() !== $market->currency()) {
            throw new InvalidArgumentException('Price currency must match the market currency.');
        }

        return $money->value->formatToLocale(
            "{$locale}-{$market->value}-u-nu-latn",
            $locale === 'ar' ? CurrencyDisplay::Symbol : CurrencyDisplay::Code,
        );
    }
}
