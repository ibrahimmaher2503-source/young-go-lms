<?php

declare(strict_types=1);

namespace App\Domain\Shared\Markets;

use DomainException;

final class MarketResolver
{
    public function checkout(string $countryCode): Market
    {
        $market = Market::tryFrom(strtoupper($countryCode));
        if ($market === null || ! $market->isActive()) {
            throw new DomainException('Checkout is unavailable in this market.');
        }

        return $market;
    }
}
