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

    /**
     * A deduction (discount, reversal) as one LTR-isolated run, so the sign always sits next to the number.
     * Arabic reads "-50.00 ج.م." (number first, currency isolated RTL); English keeps "-EGP 50.00".
     * Plain text with Unicode isolates, safe for table cells and Blade alike.
     */
    public function formatDeduction(Money $money, Market $market, string $locale): string
    {
        $absolute = Money::ofMinor(abs($money->value->getMinorAmount()->toInt()), $market->currency());
        $formatted = $this->format($absolute, $market, $locale);

        if ($locale === 'ar' && preg_match('/[0-9][0-9.,\x{066B}\x{066C}]*/u', $formatted, $match) === 1) {
            $symbol = trim((string) preg_replace('/[\x{200E}\x{200F}\x{061C}\x{00A0}\s]+/u', ' ', str_replace($match[0], '', $formatted)));

            return "\u{2066}-{$match[0]}\u{00A0}\u{2067}{$symbol}\u{2069}\u{2069}";
        }

        return "-{$formatted}";
    }
}
