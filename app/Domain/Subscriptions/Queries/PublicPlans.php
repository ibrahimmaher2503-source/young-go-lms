<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\SubscriptionPlan;
use Illuminate\Support\Collection;

/**
 * Public display hook for plans: the one definition of a sellable plan. It is active, the market is active, it has a
 * price for that market and at least one included course is published. Used by the public bundle page and the checkout (T-P8-12).
 */
final class PublicPlans
{
    /** @return Collection<int, SubscriptionPlan> */
    public function paidForMarket(Market $market): Collection
    {
        return $this->forMarket($market)->filter(static fn (SubscriptionPlan $plan): bool => $plan->priceFor($market)?->amount_minor > 0)->values();
    }

    /** @return Collection<int, SubscriptionPlan> with prices and courses.course loaded */
    public function forMarket(Market $market): Collection
    {
        if (! $market->isActive()) {
            return new Collection;
        }

        return SubscriptionPlan::query()->where('is_active', true)->with(['prices', 'courses.course'])->orderByDesc('id')->get()
            ->filter(static fn (SubscriptionPlan $p): bool => $p->priceFor($market) !== null
                && $p->courses->contains(static fn ($c): bool => $c->course?->status === CourseStatus::Published))
            ->values();
    }
}
