<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\Actions\AllocateBundlePrice;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\ProductType;
use InvalidArgumentException;

/**
 * The per-course share of one order line, for the instructor ledger and refunds. A course line is one share (its net
 * price); a bundle line returns the shares stored at order time. Pass `$netMinor` (for example the amount left after a
 * partial refund) to get the stored shares scaled proportionally to that amount; the result always sums to it.
 */
final class OrderItemAllocations
{
    public function __construct(private readonly AllocateBundlePrice $allocate) {}

    /** @return array<int, int> minor units keyed by course id */
    public function for(OrderItem $item, ?int $netMinor = null): array
    {
        $net = $netMinor ?? $item->allocated_minor ?? ($item->unit_price_minor - $item->discount_minor);
        if ($net < 0) {
            throw new InvalidArgumentException('The amount cannot be negative.');
        }
        if ($item->product_type === ProductType::Course) {
            return [$item->product_id => $net];
        }

        $rows = $item->relationLoaded('allocations') ? $item->allocations : $item->allocations()->get();
        if ($rows->isEmpty()) {
            return [];
        }
        if ($rows->sum('allocated_minor') === $net) {
            return $rows->pluck('allocated_minor', 'course_id')->map(static fn ($v): int => (int) $v)->all();
        }
        if ($rows->sum('allocated_minor') === 0) {
            return $this->allocate->split($net, $rows->pluck('weight', 'course_id')->map(static fn ($v): int => (int) $v)->all());
        }

        return $this->allocate->split($net, $rows->pluck('allocated_minor', 'course_id')->map(static fn ($v): int => max(1, (int) $v))->all());
    }
}
