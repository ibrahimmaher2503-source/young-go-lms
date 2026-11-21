<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\Actions\UpdateSourceEntitlementDates;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Allows staff (`payment.approve_manual` + `manual_entitlement.grant`) to edit or extend the latest period end date of a
 * learner's subscription, recomputing `grace_ends_at`, syncing the period-linked course entitlements via the Entitlements
 * module (I01), and optionally reactivating a cancelled or expired subscription when extended into the future.
 */
final class UpdateSubscriptionPeriod
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UpdateSourceEntitlementDates $updateEntitlements,
    ) {}

    public function __invoke(
        User $actor,
        Subscription $subscription,
        CarbonInterface $endsAt,
        bool $reactivate = true,
        ?string $reason = null,
    ): SubscriptionPeriod {
        if (
            $actor->getAttribute('status') !== 'active'
            || ! $actor->can('payment.approve_manual')
            || ! $actor->can('manual_entitlement.grant')
        ) {
            throw new AuthorizationException;
        }

        $auditReason = trim((string) ($reason ?: 'Subscription period updated'));
        if (mb_strlen($auditReason) < 3 || mb_strlen($auditReason) > 500) {
            throw ValidationException::withMessages(['reason' => __('access.reason_invalid')]);
        }

        return DB::transaction(function () use ($actor, $subscription, $endsAt, $reactivate, $auditReason): SubscriptionPeriod {
            $locked = Subscription::query()->with('plan')->lockForUpdate()->findOrFail($subscription->getKey());
            $latest = SubscriptionPeriod::query()
                ->where('subscription_id', $locked->getKey())
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            if ($latest === null) {
                throw ValidationException::withMessages(['ends_at' => __('subscriptions.not_renewable')]);
            }

            if ($endsAt->lte($latest->starts_at)) {
                throw ValidationException::withMessages(['ends_at' => __('access.ends_before_start')]);
            }

            $graceDays = (int) ($locked->plan?->grace_days ?? 3);
            $graceEndsAt = $endsAt->copy()->addDays($graceDays);

            $before = [
                'status' => $locked->status->value,
                'cancelled_at' => $locked->cancelled_at?->toIso8601String(),
                'expired_at' => $locked->expired_at?->toIso8601String(),
                'period_id' => $latest->getKey(),
                'ends_at' => $latest->ends_at->toIso8601String(),
                'grace_ends_at' => $latest->grace_ends_at->toIso8601String(),
            ];

            $latest->forceFill([
                'ends_at' => $endsAt,
                'grace_ends_at' => $graceEndsAt,
            ])->save();

            ($this->updateEntitlements)(
                $actor,
                EntitlementSource::Subscription,
                $latest->entitlementRef(),
                $latest->starts_at,
                $graceEndsAt,
                $auditReason,
            );

            if ($graceEndsAt->lte(now())) {
                $locked->forceFill([
                    'status' => SubscriptionStatus::Expired->value,
                    'expired_at' => $locked->expired_at ?? now(),
                ])->save();
            } elseif ($reactivate) {
                $locked->forceFill([
                    'status' => SubscriptionStatus::Active->value,
                    'cancelled_at' => null,
                    'expired_at' => null,
                ])->save();
            }

            $after = [
                'status' => $locked->status->value,
                'cancelled_at' => $locked->cancelled_at?->toIso8601String(),
                'expired_at' => $locked->expired_at?->toIso8601String(),
                'period_id' => $latest->getKey(),
                'ends_at' => $latest->ends_at->toIso8601String(),
                'grace_ends_at' => $latest->grace_ends_at->toIso8601String(),
                'reason' => $auditReason,
            ];

            if ($this->audit->record('subscription.updated', $locked, (int) $actor->getKey(), $before, $after) === null) {
                throw new RuntimeException('Unable to record subscription update audit.');
            }

            return $latest;
        });
    }
}
