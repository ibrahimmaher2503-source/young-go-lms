<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\Queries\CheckoutOffers;
use App\Domain\Engagement\Actions\ToggleWishlist;
use App\Domain\Engagement\Queries\WishlistQuery;
use App\Domain\Identity\ActingLearner;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** T-P9-02: the signed-in learner's saved courses and the heart toggle. Scoped to the acting learner, never to a parent. */
final class WishlistController
{
    public function index(ActingLearner $acting, WishlistQuery $wishlist, CheckoutOffers $offers): View
    {
        $learner = $acting->current();
        abort_unless($learner instanceof User, 403);

        $courses = $wishlist->courses($learner)->paginate(12);

        return view('learner.wishlist', [
            'courses' => $courses,
            'market' => Market::Egypt,
            'prices' => $offers->coursePrices($courses->items(), Market::Egypt),
        ]);
    }

    public function toggle(Request $request, int $course, ActingLearner $acting, ToggleWishlist $toggle): RedirectResponse|JsonResponse
    {
        $learner = $acting->current();
        abort_unless($learner instanceof User, 403);

        $saved = $toggle($learner, Course::query()->findOrFail($course));
        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved]);
        }

        return redirect()->to(Str::before(url()->previous(route('catalog.index')), '#').'#wishlist-'.$course);
    }
}
