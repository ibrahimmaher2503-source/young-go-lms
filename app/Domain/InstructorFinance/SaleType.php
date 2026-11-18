<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

use App\Domain\Commerce\ProductType;

enum SaleType: string
{
    case Individual = 'individual';
    case Bundle = 'bundle';
    case Subscription = 'subscription';

    public static function fromProduct(ProductType $type): self
    {
        return match ($type) {
            ProductType::Course => self::Individual,
            ProductType::Bundle => self::Bundle,
            ProductType::Plan => self::Subscription,
        };
    }
}
