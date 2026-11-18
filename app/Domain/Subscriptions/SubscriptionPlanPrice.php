<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions;

use App\Domain\Shared\Markets\Market;
use Illuminate\Database\Eloquent\Model;

/**
 * The price of a plan period in one market, in integer minor units. One row per plan and market.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $market
 * @property string $currency
 * @property int $amount_minor
 */
final class SubscriptionPlanPrice extends Model
{
    public $timestamps = false;

    protected $table = 'subscription_plan_prices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function forMarket(Market $market): bool
    {
        return $this->market === $market->value && $this->currency === $market->currency();
    }
}
