<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions;

use App\Domain\Shared\Markets\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A monthly or annual plan that gives access to its included courses only (PRD 29.7). The price lives in
 * `subscription_plan_prices`, one row per market, in integer minor units. Only an active plan can be ordered.
 *
 * @property int $id
 * @property PlanTerm $term
 * @property int $grace_days
 * @property bool $is_active
 * @property int|null $created_by
 */
final class SubscriptionPlan extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'subscription_plans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['term' => PlanTerm::class, 'is_active' => 'boolean', 'grace_days' => 'integer'];
    }

    public function priceFor(Market $market): ?SubscriptionPlanPrice
    {
        $price = $this->prices->first(static fn (SubscriptionPlanPrice $p): bool => $p->market === $market->value);

        return $price instanceof SubscriptionPlanPrice && $price->forMarket($market) ? $price : null;
    }

    /** @return HasMany<SubscriptionPlanCourse, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(SubscriptionPlanCourse::class, 'plan_id')->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<SubscriptionPlanPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanPrice::class, 'plan_id');
    }
}
