<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Subscriptions\Queries\PublicPlans;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Models\Course;
use App\Models\User;

/**
 * What the checkout may sell right now (T-P8-12): the display hook for the course and bundle call to action and the guard of
 * the checkout pages. It mirrors the rules `CreateOrder` enforces (published, individually sold, priced in an active
 * market, or an active plan with an Egypt price) so a button is shown only when an order can be created; the order itself is still priced and checked on the server.
 */
final class CheckoutOffers
{
    public function __construct(private readonly PublicBundles $bundles, private readonly PublicPlans $plans) {}

    /** The list price of a course in the market, or null when it cannot be bought on its own. */
    public function coursePrice(Course $course, Market $market): ?Money
    {
        if (! $market->isActive() || $course->status !== CourseStatus::Published || ! in_array($course->access_mode, Course::INDIVIDUAL_ACCESS_MODES, true)) {
            return null;
        }
        $price = CoursePrice::query()->where('course_id', $course->getKey())->where('market', $market->value)->first();

        return $price instanceof CoursePrice && $price->forMarket($market) && $price->amount_minor > 0 ? $price->money() : null;
    }

    /**
     * List prices of many courses with one query (public catalog cards, T-P2-04), same rules as coursePrice().
     *
     * @param  iterable<Course>  $courses
     * @return array<int, Money> course id => price; courses that cannot be bought on their own are absent
     */
    public function coursePrices(iterable $courses, Market $market): array
    {
        $sellable = [];
        foreach ($courses as $course) {
            if ($market->isActive() && $course->status === CourseStatus::Published && in_array($course->access_mode, Course::INDIVIDUAL_ACCESS_MODES, true)) {
                $sellable[] = $course->getKey();
            }
        }
        if ($sellable === []) {
            return [];
        }

        $out = [];
        foreach (CoursePrice::query()->whereIn('course_id', $sellable)->where('market', $market->value)->get() as $price) {
            if ($price->forMarket($market) && $price->amount_minor > 0) {
                $out[$price->course_id] = $price->money();
            }
        }

        return $out;
    }

    /** A sellable bundle (see PublicBundles) with prices and member courses loaded, or null. */
    public function bundle(int $id, Market $market): ?Bundle
    {
        $bundle = $this->bundles->forMarket($market)->first(static fn (Bundle $b): bool => $b->getKey() === $id);

        if (! $bundle instanceof Bundle) {
            return null;
        }
        $price = $bundle->priceFor($market);

        return $price !== null && $price->amount_minor > 0 ? $bundle : null;
    }

    /** A sellable plan (see PublicPlans) with prices and courses loaded and a price above zero, or null. */
    public function plan(int $id, Market $market): ?SubscriptionPlan
    {
        $plan = $this->plans->forMarket($market)->first(static fn (SubscriptionPlan $p): bool => $p->getKey() === $id);
        if (! $plan instanceof SubscriptionPlan) {
            return null;
        }
        $price = $plan->priceFor($market);

        return $price !== null && $price->amount_minor > 0 ? $plan : null;
    }

    /** True when the learner's paid period still runs beyond the renewal window, so CreateOrder would refuse a new plan order (`already_subscribed`). */
    public function planCovered(int $planId, User $learner): bool
    {
        $latest = Subscription::query()->where(['plan_id' => $planId, 'learner_id' => $learner->getKey()])->first()?->latestPeriod();

        return $latest !== null && $latest->ends_at->gt(now()->addDays((int) config('subscriptions.renewal_window_days', 7)));
    }
}
