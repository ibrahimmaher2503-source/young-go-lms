<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\CancelSubscription;
use App\Domain\Subscriptions\Actions\RenewSubscription;
use App\Domain\Subscriptions\Queries\AccountSubscriptions;
use App\Domain\Subscriptions\Subscription;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * T-P8-08 / T-P8-12: the signed-in account's own subscriptions (as learner or payer): plan, paid dates, state, manual renewal
 * (creates a pending order and goes to checkout) and cancellation. Thin: renewal and cancellation rules live in the Subscriptions Actions.
 */
final class SubscriptionController
{
    public function index(Request $request, AccountSubscriptions $subscriptions): View
    {
        return view('learner.subscription', ['subscriptions' => $subscriptions->for($this->user($request)), 'market' => Market::Egypt]);
    }

    public function renew(Request $request, int $subscription, AccountSubscriptions $subscriptions, RenewSubscription $renew): RedirectResponse
    {
        $user = $this->user($request);
        $order = $renew($user, $this->own($user, $subscription, $subscriptions));

        return redirect()->route('checkout.order', $order);
    }

    public function cancel(Request $request, int $subscription, AccountSubscriptions $subscriptions, CancelSubscription $cancel): RedirectResponse
    {
        $user = $this->user($request);
        $cancel($user, $this->own($user, $subscription, $subscriptions));

        return redirect()->route('subscription.index')->with('subscription_notice', __('subscriptions.my_cancel_done'));
    }

    private function own(User $user, int $id, AccountSubscriptions $subscriptions): Subscription
    {
        $found = $subscriptions->find($user, $id);
        abort_if($found === null, 404);

        return $found;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->getAttribute('status') === 'active', 404);

        return $user;
    }
}
