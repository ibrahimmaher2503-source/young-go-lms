<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Catalog\Contracts\CourseHistoryChecker;
use App\Models\Course;

/** A course referenced by any direct order line or bundle allocation is financial history and must be archived, never deleted. */
final class OrderHistory implements CourseHistoryChecker
{
    public function blocks(Course $course): ?string
    {
        $orderedDirectly = OrderItem::query()
            ->where('product_type', ProductType::Course->value)
            ->where('product_id', $course->getKey())
            ->exists();

        if ($orderedDirectly || OrderItemAllocation::query()->where('course_id', $course->getKey())->exists()) {
            return 'orders';
        }

        return null;
    }
}
