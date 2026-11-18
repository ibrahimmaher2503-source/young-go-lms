<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels a subscription (YG-GAP-SUB-004). Access stays until the end of each paid period: the plan entitlements are
 * cut from period end plus grace to the period end, so the grace days do not apply once someone cancels. No reminders or
 * renewal prompts follow, a past order is never refunded or prorated, and progress is kept. Allowed for the subscriber, the
 * payer, a linked parent of the child, and staff with `payment.approve_manual`. Cancelling twice is a no-op. Paying a new
 * renewal order later reactivates it.
 */
final class CancelSubscription
{
    public function __construct(private readonly AuditLogger $audit, private readonly EntitlementService $entitlements) {}

    public function __invoke(User $actor, Subscription $subscription): Subscription
    {
        if ($actor->getAttribute('status') !== 'active' || ! $this->mayCancel($actor, $subscription)) {
            throw new AuthorizationException;
        }

        $result = DB::transaction(function () use ($actor, $subscription): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            if ($locked->cancelled_at !== null || $locked->status === SubscriptionStatus::Expired) {
                return $locked;
            }

            $locked->forceFill(['cancelled_at' => now()])->save();
            foreach ($locked->periods()->get() as $period) {
                Entitlement::query()->where('source_type', EntitlementSource::Subscription->value)->where('source_ref', $period->entitlementRef())
                    ->where('status', Entitlement::ACTIVE)->where('ends_at', '>', $period->ends_at)->update(['ends_at' => $period->ends_at]);
            }

            if ($this->audit->record('subscription.cancelled', $locked, (int) $actor->getKey(), ['cancelled' => false], ['cancelled' => true]) === null) {
                throw new RuntimeException('Unable to record subscription audit.');
            }

            return $locked;
        });

        $this->entitlements->flush();

        return $result;
    }

    private function mayCancel(User $actor, Subscription $subscription): bool
    {
        if ($actor->can('payment.approve_manual') || $actor->getKey() === $subscription->learner_id || $actor->getKey() === $subscription->payer_id) {
            return true;
        }

        $learner = $subscription->learner;

        return $learner instanceof User && GuardianAccess::parentCanAccessChild($actor, $learner)->allowed();
    }
}
