<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use RuntimeException;

/**
 * Gives every learner on a paid order the course they bought. Idempotent through GrantEntitlement (source, order
 * reference, learner, course is one row). Used by manual payment approval; the Paymob fulfilment (T-P8-03) should call it
 * with `EntitlementSource::Purchase`. Only course lines are supported until bundles and plans exist; other lines are skipped, so callers refuse them first.
 */
final class GrantOrderEntitlements
{
    public function __construct(private readonly GrantEntitlement $grant) {}

    /** @return int number of lines handled */
    public function __invoke(Order $order, EntitlementSource $source, ?User $actor = null): int
    {
        $handled = 0;
        foreach ($order->items()->get() as $item) {
            if ($item->product_type !== ProductType::Course) {
                continue;
            }
            $learner = User::query()->find($item->learner_id);
            $course = Course::query()->find($item->product_id);
            if ($learner === null || $course === null) {
                throw new RuntimeException('Order line points at a missing learner or course.');
            }
            ($this->grant)($learner, $course, $source, 'order:'.$order->reference, null, null, $actor, $source->isManual() ? 'order '.$order->reference : null);
            $handled++;
        }

        return $handled;
    }
}
