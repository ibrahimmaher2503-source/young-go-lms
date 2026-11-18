<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dashboards;

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use Throwable;

/** Formats minor units in their own currency and the current locale (never mixes currencies). */
final class DashboardMoney
{
    public static function format(int $minor, string $currency): string
    {
        $market = null;
        foreach (Market::cases() as $candidate) {
            if ($candidate->currency() === $currency) {
                $market = $candidate;
                break;
            }
        }

        if ($market === null) {
            return number_format($minor / 100, 2, '.', ',').' '.$currency;
        }

        try {
            return (new PriceFormatter)->format(Money::ofMinor($minor, $currency), $market, app()->getLocale());
        } catch (Throwable) {
            return number_format($minor / 100, 2, '.', ',').' '.$currency;
        }
    }
}
