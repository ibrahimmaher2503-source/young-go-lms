<?php

declare(strict_types=1);

namespace App\Domain\Shared\Money;

use Brick\Money\Money as BrickMoney;
use TypeError;

final readonly class Money
{
    private function __construct(public BrickMoney $value) {}

    public static function ofMinor(mixed $amount, string $currency): self
    {
        if (! is_int($amount)) {
            throw new TypeError('Minor units must be an integer.');
        }

        return new self(BrickMoney::ofMinor($amount, $currency));
    }
}
