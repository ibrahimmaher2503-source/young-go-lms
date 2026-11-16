<?php

declare(strict_types=1);

namespace App\Filament\Shared\Tables;

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class MoneyColumn extends TextColumn
{
    public static function make(?string $name = null, string $currencyAttribute = 'currency'): static
    {
        return parent::make($name)->formatStateUsing(static function (mixed $state, ?Model $relatedRecord) use ($currencyAttribute): ?string {
            if ($state === null) {
                return null;
            }

            $currency = $relatedRecord?->getAttribute($currencyAttribute);
            if (! is_string($currency) || $currency === '') {
                throw new InvalidArgumentException('A currency attribute is required to format a money column.');
            }

            $market = null;
            foreach (Market::cases() as $candidate) {
                if ($candidate->currency() === $currency) {
                    $market = $candidate;
                    break;
                }
            }

            if ($market === null) {
                throw new InvalidArgumentException("No configured market uses currency [{$currency}].");
            }

            return (new PriceFormatter)->format(Money::ofMinor($state, $currency), $market, app()->getLocale());
        });
    }
}
