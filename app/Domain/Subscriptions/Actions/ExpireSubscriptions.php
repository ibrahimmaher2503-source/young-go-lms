<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\Actions\ExpireEntitlements;
use App\Domain\Subscriptions\Events\SubscriptionExpired;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Scheduled sweep (YG-GAP-SUB-001): a subscription whose last paid period and grace have both passed is marked expired.
 * Access itself ends through EntitlementService: the plan entitlements end at period end plus grace (cancelled ones at
 * period end), the sweep then marks them expired through ExpireEntitlements. Only entitlements with source `subscription`
 * are involved here, so courses the learner bought individually or through a bundle are untouched, and progress,
 * submissions and certificates are kept for a later renewal (YG-GAP-SUB-002). Safe to run as often as wanted.
 */
final class ExpireSubscriptions
{
    public function __construct(private readonly AuditLogger $audit, private readonly ExpireEntitlements $expireEntitlements) {}

    /** @return int subscriptions marked expired in this run */
    public function __invoke(): int
    {
        $count = 0;
        Subscription::query()->where('status', SubscriptionStatus::Active->value)
            ->whereDoesntHave('periods', static fn ($q) => $q->where('grace_ends_at', '>', now()))
            ->chunkById(200, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    /** @var Subscription $row */
                    $count += $this->expire($row) ? 1 : 0;
                }
            });

        ($this->expireEntitlements)();

        return $count;
    }

    private function expire(Subscription $row): bool
    {
        return DB::transaction(function () use ($row): bool {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($row->getKey());
            if ($locked->status !== SubscriptionStatus::Active || $locked->periods()->where('grace_ends_at', '>', now())->exists()) {
                return false;
            }

            $locked->forceFill(['status' => SubscriptionStatus::Expired->value, 'expired_at' => now()])->save();
            $recorded = $this->audit->record('subscription.expired', $locked, null, ['status' => SubscriptionStatus::Active->value], [
                'status' => SubscriptionStatus::Expired->value, 'plan_id' => $locked->plan_id, 'learner_id' => $locked->learner_id,
            ]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record subscription audit.');
            }
            DB::afterCommit(static fn () => event(new SubscriptionExpired($locked)));

            return true;
        });
    }
}
