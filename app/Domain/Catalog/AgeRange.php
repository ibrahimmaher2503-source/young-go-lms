<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use DomainException;

final readonly class AgeRange
{
    public function __construct(
        public ?int $min,
        public ?int $max,
    ) {
        if (($min !== null && $min < 0) || ($max !== null && $max < 0) || ($min !== null && $max !== null && $min > $max)) {
            throw new DomainException('Age ranges must be non-negative and ordered.');
        }
    }

    public function includes(int $age): bool
    {
        return $age >= 0
            && ($this->min === null || $age >= $this->min)
            && ($this->max === null || $age <= $this->max);
    }
}
