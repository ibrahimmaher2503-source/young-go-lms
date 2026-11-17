<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Models\User;

/** One requested purchase: what, and for whom. It carries no price on purpose; the server prices every line. */
final readonly class OrderLine
{
    public function __construct(public ProductType $type, public int $productId, public User $learner) {}
}
