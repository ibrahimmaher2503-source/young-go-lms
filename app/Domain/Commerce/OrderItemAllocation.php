<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Entitlements\Entitlement;
use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One member course of a bundle order line: its allocation weight and its share of the line price in minor units
 * (the shares add up to the line price exactly), written when the order is created and never repriced. Granting fills
 * `entitlement_id`, or sets `skipped_existing` when the learner already had the course. The ledger and refunds read it.
 *
 * @property int $id
 * @property int $order_item_id
 * @property int $course_id
 * @property int $weight
 * @property int $allocated_minor
 * @property int|null $entitlement_id
 * @property bool $skipped_existing
 * @property Carbon|null $granted_at
 */
final class OrderItemAllocation extends Model
{
    protected $table = 'order_item_allocations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weight' => 'integer', 'allocated_minor' => 'integer', 'skipped_existing' => 'boolean', 'granted_at' => 'datetime'];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Entitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(Entitlement::class);
    }
}
