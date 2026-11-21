<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\Queries\CheckoutOffers;
use App\Domain\Seo\OfferSeo;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Queries\PublicPlans;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Public plans page (T-P8-12): the sellable plans of PublicPlans with an Egypt price. No such plan = 404. */
final class PublicPlansController
{
    public function __invoke(Request $request, PublicPlans $plans, CheckoutOffers $offers): View
    {
        $market = Market::Egypt;
        $listed = $plans->paidForMarket($market);
        abort_if($listed->isEmpty(), 404);

        return view('catalog.plans', [
            'plans' => $listed,
            'bundles' => $offers->sellableBundles($market),
            'market' => $market,
            'signedIn' => $request->user() !== null,
            'seo' => OfferSeo::plans(),
        ]);
    }
}
