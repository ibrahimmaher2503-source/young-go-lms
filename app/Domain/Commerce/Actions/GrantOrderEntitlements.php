<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderItemAllocation;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Subscriptions\Actions\StartSubscriptionPeriod;
use App\Models\Course;
use App\Models\User;
use RuntimeException;

/**
 * Gives every learner on a paid order what they bought. Idempotent through GrantEntitlement (source, order reference,
 * learner, course is one row). Used by manual payment approval and the Paymob fulfilment (`EntitlementSource::Purchase`).
 * Course lines are granted first. A bundle line grants each course of its stored allocation once with source `bundle`;
 * a course the learner already holds (another source, or a course line on this order) is not granted again but the
 * allocation row records it as skipped. A plan line starts or renews a subscription period (StartSubscriptionPeriod, source `subscription`).
 */
final class GrantOrderEntitlements
{
    public function __construct(
        private readonly GrantEntitlement $grant,
        private readonly EntitlementService $entitlements,
        private readonly AuditLogger $audit,
        private readonly StartSubscriptionPeriod $startPeriod,
    ) {}

    /** @return int number of lines handled */
    public function __invoke(Order $order, EntitlementSource $source, ?User $actor = null): int
    {
        $handled = 0;
        $items = $order->items()->orderBy('id')->get();

        foreach ($items->where('product_type', ProductType::Course) as $item) {
            $learner = User::query()->find($item->learner_id);
            $course = Course::query()->find($item->product_id);
            if ($learner === null || $course === null) {
                throw new RuntimeException('Order line points at a missing learner or course.');
            }
            ($this->grant)($learner, $course, $source, 'order:'.$order->reference, null, null, $actor, $source->isManual() ? 'order '.$order->reference : null);
            $handled++;
        }

        foreach ($items->where('product_type', ProductType::Bundle) as $item) {
            $this->grantBundle($order, $item, $actor);
            $handled++;
        }

        foreach ($items->where('product_type', ProductType::Plan) as $item) {
            ($this->startPeriod)($order, $item, $actor);
            $handled++;
        }

        return $handled;
    }

    private function grantBundle(Order $order, OrderItem $item, ?User $actor): void
    {
        $learner = User::query()->find($item->learner_id);
        $allocations = OrderItemAllocation::query()->where('order_item_id', $item->getKey())->orderBy('id')->get();
        if ($learner === null || $allocations->isEmpty()) {
            throw new RuntimeException('Bundle line has no learner or no allocation.');
        }

        $ref = 'order:'.$order->reference;
        $granted = [];
        $skipped = [];
        foreach ($allocations as $allocation) {
            $course = Course::query()->find($allocation->course_id);
            if ($course === null) {
                throw new RuntimeException('Bundle allocation points at a missing course.');
            }

            $own = Entitlement::query()->where(['source_type' => EntitlementSource::Bundle->value, 'source_ref' => $ref, 'learner_id' => $learner->getKey(), 'course_id' => $course->getKey()])->first();
            if ($own === null && $this->entitlements->currentFor($learner, $course) !== null) {
                if (! $allocation->skipped_existing) {
                    $allocation->forceFill(['skipped_existing' => true, 'granted_at' => now()])->save();
                    $skipped[] = $course->getKey();
                }

                continue;
            }

            $entitlement = $own ?? ($this->grant)($learner, $course, EntitlementSource::Bundle, $ref, null, null, $actor);
            if ($allocation->entitlement_id !== $entitlement->getKey()) {
                $allocation->forceFill(['entitlement_id' => $entitlement->getKey(), 'skipped_existing' => false, 'granted_at' => $allocation->granted_at ?? now()])->save();
                $granted[] = $course->getKey();
            }
        }

        if (($granted !== [] || $skipped !== []) && $this->audit->record('bundle.granted', $item, $actor === null ? null : (int) $actor->getKey(), null, [
            'order' => $order->reference, 'learner_id' => $learner->getKey(), 'granted_course_ids' => $granted, 'skipped_course_ids' => $skipped,
        ]) === null) {
            throw new RuntimeException('Unable to record bundle grant audit.');
        }
    }
}
