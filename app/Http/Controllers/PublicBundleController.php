<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\Queries\CheckoutOffers;
use App\Domain\Commerce\Queries\PublicBundles;
use App\Domain\Seo\OfferSeo;
use App\Domain\Shared\Markets\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Public bundle entry page (T-P8-12): the courses of a sellable bundle, its price and a link to checkout. Unknown or unsellable = 404. */
final class PublicBundleController
{
    public function __invoke(Request $request, string $slug, CheckoutOffers $offers): View
    {
        $id = PublicBundles::idFromKey($slug);
        $market = Market::Egypt;
        $bundle = $id === null ? null : $offers->bundle($id, $market);
        $price = $bundle?->priceFor($market);
        abort_if($bundle === null || $price === null, 404);

        return view('catalog.bundle', [
            'bundle' => $bundle, 'price' => $price->money(), 'market' => $market,
            'signedIn' => $request->user() !== null, 'seo' => OfferSeo::bundle($bundle),
        ]);
    }
}
