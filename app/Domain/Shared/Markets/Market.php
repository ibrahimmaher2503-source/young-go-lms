<?php

declare(strict_types=1);

namespace App\Domain\Shared\Markets;

use Illuminate\Support\Facades\Config;

enum Market: string
{
    case Egypt = 'EG';
    case SaudiArabia = 'SA';

    public function currency(): string
    {
        return Config::string("markets.{$this->value}.currency");
    }

    public function timezone(): string
    {
        return Config::string("markets.{$this->value}.timezone");
    }

    public function isActive(): bool
    {
        // T-P12-01 owns the Saudi activation path and its approval checks.
        return $this === self::Egypt && Config::boolean("markets.{$this->value}.active");
    }
}
