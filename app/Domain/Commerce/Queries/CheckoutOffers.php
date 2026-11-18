<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Models\Course;

/**
 * What the checkout may sell right now (T-P8-12): the display hook for the course and bundle call to action and the guard of
 * the checkout pages. It mirrors the rules `CreateOrder` enforces (published, individually sold, priced in an active
 * market) so a button is shown only when an order can be created; the order itself is still priced and checked on the server.
 */
final class CheckoutOffers
{
    public function __construct(private readonly PublicBundles $bundles) {}

    /** The list price of a course in the market, or null when it cannot be bought on its own. */
    public function coursePrice(Course $course, Market $market): ?Money
    {
        if (! $market->isActive() || $course->status !== CourseStatus::Published || ! in_array($course->access_mode, Course::INDIVIDUAL_ACCESS_MODES, true)) {
            return null;
        }
        $price = CoursePrice::query()->where('course_id', $course->getKey())->where('market', $market->value)->first();

        return $price instanceof CoursePrice && $price->forMarket($market) && $price->amount_minor > 0 ? $price->money() : null;
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
}
