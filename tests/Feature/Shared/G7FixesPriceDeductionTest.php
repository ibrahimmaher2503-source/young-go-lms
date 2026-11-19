<?php

declare(strict_types=1);

// T-G7-FIXES-D item 1 | PriceFormatter::formatDeduction (sign next to the number, currency isolated in AR)

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use InvalidArgumentException;

it('formats an English deduction as the currency code followed by the amount, with the sign in front', function (): void {
    $text = (new PriceFormatter)->formatDeduction(Money::ofMinor(5000, 'EGP'), Market::Egypt, 'en');

    expect($text)->toMatch('/^-EGP[\x{00A0} ]50\.00$/u');
});

it('formats an Arabic deduction with the number first and the currency isolated, LTR number inside RTL isolates', function (): void {
    $text = (new PriceFormatter)->formatDeduction(Money::ofMinor(5000, 'EGP'), Market::Egypt, 'ar');

    expect($text)->toStartWith("\u{2066}-50.00\u{00A0}\u{2067}")
        ->and($text)->toEndWith("\u{2069}\u{2069}")
        ->and($text)->toContain('ج.م.');
});

it('gives the same text for a negative amount and its absolute value', function (string $locale): void {
    $formatter = new PriceFormatter;

    expect($formatter->formatDeduction(Money::ofMinor(-5000, 'EGP'), Market::Egypt, $locale))
        ->toBe($formatter->formatDeduction(Money::ofMinor(5000, 'EGP'), Market::Egypt, $locale));
})->with(['en', 'ar']);

it('refuses a deduction in a currency other than the market currency', function (): void {
    expect(fn () => (new PriceFormatter)->formatDeduction(Money::ofMinor(5000, 'USD'), Market::Egypt, 'en'))
        ->toThrow(InvalidArgumentException::class);
});
