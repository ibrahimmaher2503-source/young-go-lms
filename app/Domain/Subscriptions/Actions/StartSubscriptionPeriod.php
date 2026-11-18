<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Subscriptions\Events\SubscriptionRenewed;
use App\Domain\Subscriptions\Events\SubscriptionStarted;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns one paid plan order line into a subscription period and the access it gives (called from GrantOrderEntitlements
 * after payment is verified or a manual proof is approved). The first paid line creates the subscription; every later one
 * is a renewal. A renewal bought before the current period ends starts when it ends (nothing is prorated or lost); one
 * bought during grace or after expiry starts now. Every included course is granted with source `subscription`, reference
 * `subscription_period:<id>`, from the period start to the period end plus the grace days. Idempotent: the same order line
 * is one period (unique key), so a replayed webhook or approval changes nothing. A lapsed or cancelled subscription is
 * reactivated. Progress and submissions are never touched, so they are there again after renewal (YG-GAP-SUB-002).
 */
final class StartSubscriptionPeriod
{
    public function __construct(private readonly AuditLogger $audit, private readonly GrantEntitlement $grant) {}

    public function __invoke(Order $order, OrderItem $item, ?User $actor = null): SubscriptionPeriod
    {
        if ($item->product_type !== ProductType::Plan || $item->order_id !== $order->getKey()) {
            throw new RuntimeException('Not a plan line of this order.');
        }

        return DB::transaction(function () use ($order, $item, $actor): SubscriptionPeriod {
            $existing = SubscriptionPeriod::query()->where('order_item_id', $item->getKey())->first();
            if ($existing !== null) {
                return $existing;
            }

            $plan = SubscriptionPlan::query()->with('courses')->find($item->product_id);
            $learner = User::query()->find($item->learner_id);
            if ($plan === null || $learner === null || $plan->courses->isEmpty()) {
                throw new RuntimeException('Plan line points at a missing plan, learner or courses.');
            }

            [$subscription, $created] = $this->subscription($plan, $learner, $order);
            $latest = SubscriptionPeriod::query()->where('subscription_id', $subscription->getKey())->orderByDesc('sequence')->first();

            $startsAt = $latest !== null && $latest->ends_at->gt(now()) ? $latest->ends_at->copy() : now();
            $endsAt = $plan->term->endFrom($startsAt);
            $graceEndsAt = $endsAt->addDays($plan->grace_days);

            $period = SubscriptionPeriod::query()->create([
                'subscription_id' => $subscription->getKey(), 'sequence' => $latest === null ? 1 : $latest->sequence + 1, 'order_item_id' => $item->getKey(),
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'grace_ends_at' => $graceEndsAt,
            ]);

            foreach ($plan->courses as $planCourse) {
                $course = Course::query()->find($planCourse->course_id);
                if ($course === null) {
                    throw new RuntimeException('Plan course is missing.');
                }
                ($this->grant)($learner, $course, EntitlementSource::Subscription, $period->entitlementRef(), $startsAt, $graceEndsAt, $actor);
            }

            if (! $created) {
                // A renewal reactivates a lapsed or cancelled subscription and makes the paying account the reminder recipient.
                $subscription->forceFill(['status' => SubscriptionStatus::Active->value, 'cancelled_at' => null, 'expired_at' => null, 'payer_id' => $order->payer_id])->save();
            }

            $recorded = $this->audit->record($created ? 'subscription.started' : 'subscription.renewed', $subscription, $actor === null ? null : (int) $actor->getKey(), null, [
                'order' => $order->reference, 'plan_id' => $plan->getKey(), 'learner_id' => $learner->getKey(), 'sequence' => $period->sequence,
                'starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String(), 'grace_ends_at' => $graceEndsAt->toIso8601String(),
            ]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record subscription audit.');
            }

            DB::afterCommit(static fn () => event($created ? new SubscriptionStarted($period) : new SubscriptionRenewed($period)));

            return $period;
        });
    }

    /** @return array{0: Subscription, 1: bool} the locked subscription and whether it was just created */
    private function subscription(SubscriptionPlan $plan, User $learner, Order $order): array
    {
        $keys = ['plan_id' => $plan->getKey(), 'learner_id' => $learner->getKey()];
        $found = Subscription::query()->where($keys)->lockForUpdate()->first();
        if ($found !== null) {
            return [$found, false];
        }

        try {
            return [Subscription::query()->create($keys + ['payer_id' => $order->payer_id, 'status' => SubscriptionStatus::Active->value, 'started_at' => now()]), true];
        } catch (UniqueConstraintViolationException) {
            return [Subscription::query()->where($keys)->lockForUpdate()->firstOrFail(), false]; // a concurrent line won the race
        }
    }
}
