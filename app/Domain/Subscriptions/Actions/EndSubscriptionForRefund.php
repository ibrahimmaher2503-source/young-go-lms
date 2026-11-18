<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Order;
use App\Domain\Entitlements\Actions\RevokeEntitlementsForSource;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ends the subscription access an order paid for (T-P8-06 gap, called by ExecuteRefund when the refund policy revokes access).
 * For every period created from a plan line of the order: the entitlements with source `subscription` and that period's
 * reference are revoked through the Entitlements module, and the period is closed now (its end and grace end are cut to now,
 * or to its start when it has not begun), so reminders stop and a later renewal starts from today. When no other period of
 * the subscription still gives access, the subscription is marked expired. Other periods (an earlier paid one) are untouched.
 * Safe to repeat: already revoked entitlements and already closed periods change nothing. The actor needs the grant permission
 * (checked by ExecuteRefund before money moves, and again by RevokeEntitlement).
 */
final class EndSubscriptionForRefund
{
    public function __construct(private readonly AuditLogger $audit, private readonly RevokeEntitlementsForSource $revoke) {}

    /** @return int entitlements revoked */
    public function __invoke(User $actor, Order $order, string $reason): int
    {
        $periods = SubscriptionPeriod::query()->whereHas('orderItem', static fn ($items) => $items->where('order_id', $order->getKey()))->orderBy('id')->get();
        if ($periods->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($actor, $order, $reason, $periods): int {
            $revoked = 0;
            $closed = [];
            foreach ($periods as $period) {
                $revoked += ($this->revoke)($actor, [EntitlementSource::Subscription], $period->entitlementRef(), $reason);
                $this->close($period);
                $closed[$period->subscription_id][] = $period->getKey();
            }

            foreach ($closed as $subscriptionId => $periodIds) {
                $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscriptionId);
                $stillOpen = $subscription->periods()->where('grace_ends_at', '>', now())->exists();
                $before = ['status' => $subscription->status->value];
                if (! $stillOpen && $subscription->status !== SubscriptionStatus::Expired) {
                    $subscription->forceFill(['status' => SubscriptionStatus::Expired->value, 'expired_at' => now()])->save();
                }

                $recorded = $this->audit->record('subscription.refunded', $subscription, (int) $actor->getKey(), $before, [
                    'status' => $subscription->status->value, 'order' => $order->reference, 'period_ids' => $periodIds,
                    'plan_id' => $subscription->plan_id, 'learner_id' => $subscription->learner_id,
                ]);
                if ($recorded === null) {
                    throw new RuntimeException('Unable to record subscription audit.');
                }
            }

            return $revoked;
        });
    }

    private function close(SubscriptionPeriod $period): void
    {
        $end = $period->starts_at->gt(now()) ? $period->starts_at->copy() : now();
        $period->forceFill([
            'ends_at' => $period->ends_at->gt($end) ? $end : $period->ends_at,
            'grace_ends_at' => $period->grace_ends_at->gt($end) ? $end : $period->grace_ends_at,
        ])->save();
    }
}
