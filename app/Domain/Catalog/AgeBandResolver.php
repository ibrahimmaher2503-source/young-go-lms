<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Catalog\Models\AgeBand;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class AgeBandResolver
{
    public function resolve(?int $birthYear, ?int $birthMonth): ?AgeBand
    {
        if ($birthYear === null || $birthMonth === null) {
            return null;
        }

        if ($birthMonth < 1 || $birthMonth > 12) {
            throw new InvalidArgumentException('Birth month must be between 1 and 12.');
        }

        $today = CarbonImmutable::now();
        $age = $today->year - $birthYear - ($today->month < $birthMonth ? 1 : 0);

        if ($age < 0) {
            return null;
        }

        return AgeBand::query()
            ->where('min', '<=', $age)
            ->where(fn ($query) => $query->whereNull('max')->orWhere('max', '>=', $age))
            ->orderBy('sort')
            ->orderBy('id')
            ->first();
    }
}
