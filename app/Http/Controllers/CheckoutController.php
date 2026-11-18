<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\Actions\ApplyCoupon;
use App\Domain\Commerce\Actions\RemoveCoupon;
use App\Domain\Commerce\Actions\StartCheckout;
use App\Domain\Commerce\Actions\StartCheckoutOrder;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\CheckoutLearners;
use App\Domain\Commerce\Queries\CheckoutOffers;
use App\Domain\Commerce\Queries\EnabledPaymentMethods;
use App\Domain\Content\Queries\PublicSitePages;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Domain\Seo\PublicUrl;
use App\Domain\Settings\Queries\ManualPaymentPayee;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * T-P8-12: the learner checkout. Thin on purpose: every price, coupon and payment rule lives in the Commerce Actions. The payer is
 * always the signed-in account (never the child a parent is acting as); the acting learner only picks the default learner line.
 * Every page is read-only (no GET writes), orders are found by payer, and the status shown is always the stored server state.
 */
final class CheckoutController
{
    private const string MANUAL = 'manual';

    public function product(Request $request, string $type, int $id, CheckoutOffers $offers, CheckoutLearners $learners, ActingLearner $acting, EntitlementService $access): View
    {
        $payer = $this->payer($request);
        $market = Market::Egypt;
        $product = $this->resolve($type, $id, $offers, $market);

        $options = $learners($payer);
        $default = $acting->current();
        $owned = [];
        foreach ($options as $learner) {
            if ($product['course'] instanceof Course) {
                $owned[$learner->getKey()] = $access->currentFor($learner, $product['course']) !== null;
            } elseif ($product['plan'] instanceof SubscriptionPlan) {
                $owned[$learner->getKey()] = $offers->planCovered($product['plan']->getKey(), $learner);
            }
        }

        return view('checkout.product', $product + [
            'type' => $type, 'id' => $id, 'market' => $market, 'learners' => $options, 'owned' => $owned,
            'cover' => $product['course'] instanceof Course ? $this->coverUrl($product['course']) : null,
            'selected' => (int) old('learner_id', $default instanceof User ? $default->getKey() : $payer->getKey()),
            'key' => 'co-'.bin2hex(random_bytes(12)),
        ]);
    }

    public function start(Request $request, CheckoutOffers $offers, CheckoutLearners $learners, StartCheckoutOrder $start): RedirectResponse
    {
        $payer = $this->payer($request);
        $data = $request->validate([
            'type' => ['required', Rule::in([ProductType::Course->value, ProductType::Bundle->value, ProductType::Plan->value])],
            'product_id' => ['required', 'integer', 'min:1'],
            'learner_id' => ['required', 'integer', 'min:1'],
            'key' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-:.]{8,64}$/'],
        ]);

        $learner = $learners($payer)->first(static fn (User $u): bool => $u->getKey() === (int) $data['learner_id']);
        if (! $learner instanceof User) {
            throw ValidationException::withMessages(['learner_id' => __('checkout.learner_invalid')]);
        }
        $this->resolve((string) $data['type'], (int) $data['product_id'], $offers, Market::Egypt); // 404 when not sellable

        $order = $start($payer, Market::Egypt, new OrderLine(ProductType::from((string) $data['type']), (int) $data['product_id'], $learner), (string) $data['key']);

        return redirect()->route('checkout.order', $order);
    }

    public function order(Request $request, Order $order, EnabledPaymentMethods $methods, PublicSitePages $pages): View
    {
        $this->owns($this->payer($request), $order);
        $order->load('items.learner');

        $redemption = CouponRedemption::query()->with('coupon')->where('order_id', $order->getKey())->where('status', CouponRedemptionStatus::Reserved->value)->first();
        $terms = $pages()->get('terms');
        $payee = app(ManualPaymentPayee::class)();

        return view('checkout.order', [
            'order' => $order,
            'payer' => $this->payer($request),
            'covers' => $this->covers($order),
            'market' => Market::tryFrom($order->market) ?? Market::Egypt,
            'methods' => ($methods)(),
            'coupon' => $redemption?->coupon,
            'proof' => ManualPaymentProof::query()->where('order_id', $order->getKey())->latest('id')->first(),
            'attempt' => PaymentAttempt::query()->where('order_id', $order->getKey())->latest('sequence')->first(),
            'termsUrl' => $terms !== null ? PublicUrl::page($terms, app()->getLocale()) : null,
            'payee' => $payee,
        ]);
    }

    public function applyCoupon(Request $request, Order $order, ApplyCoupon $apply): RedirectResponse
    {
        $payer = $this->payer($request);
        $this->owns($payer, $order);
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        $apply($order, $payer, (string) $data['code']);

        return redirect()->route('checkout.order', $order)->with('checkout_notice', __('checkout.coupon_applied'));
    }

    public function removeCoupon(Request $request, Order $order, RemoveCoupon $remove): RedirectResponse
    {
        $payer = $this->payer($request);
        $this->owns($payer, $order);

        $remove($order, $payer);

        return redirect()->route('checkout.order', $order)->with('checkout_notice', __('checkout.coupon_removed'));
    }

    public function pay(Request $request, Order $order, StartCheckout $start, EnabledPaymentMethods $methods): RedirectResponse
    {
        $payer = $this->payer($request);
        $this->owns($payer, $order);
        $allowed = array_merge(array_column(($methods)(), 'key'), [self::MANUAL]);
        $data = $request->validate([
            'method' => ['required', 'string', Rule::in($allowed)],
            'terms' => ['accepted'],
        ], ['terms.accepted' => __('checkout.terms_required'), 'method.required' => __('checkout.method_required'), 'method.in' => __('checkout.method_required')]);

        if ($data['method'] === self::MANUAL) {
            return redirect()->route('manual-payments.create', $order);
        }

        $attempt = $start($payer, $order, (string) $data['method']);
        if ($attempt->checkout_url === null) {
            throw ValidationException::withMessages(['payment' => __('payments.checkout_failed')]);
        }

        return redirect()->away($attempt->checkout_url);
    }

    /** Where the gateway sends the payer back. Nothing the browser carries is trusted: this only finds the order and shows its stored state. */
    public function return(Request $request): RedirectResponse
    {
        $payer = $this->payer($request);
        $reference = $request->query('order');
        $order = is_string($reference) ? Order::query()->where('reference', $reference)->where('payer_id', $payer->getKey())->first() : null;
        abort_if($order === null, 404);

        return redirect()->route('checkout.order', $order);
    }

    /** @return array{course: Course|null, bundle: Bundle|null, plan: SubscriptionPlan|null, title: string, price: Money, back: string} */
    private function resolve(string $type, int $id, CheckoutOffers $offers, Market $market): array
    {
        if ($type === ProductType::Plan->value) {
            $plan = $offers->plan($id, $market);
            abort_if($plan === null, 404);
            $price = $plan->priceFor($market);
            abort_if($price === null, 404);

            return ['course' => null, 'bundle' => null, 'plan' => $plan, 'title' => (string) $plan->name, 'price' => Money::ofMinor($price->amount_minor, $price->currency), 'back' => route('plans.index')];
        }

        if ($type === ProductType::Bundle->value) {
            $bundle = $offers->bundle($id, $market);
            abort_if($bundle === null, 404);
            $price = $bundle->priceFor($market);
            abort_if($price === null, 404);

            return ['course' => null, 'bundle' => $bundle, 'plan' => null, 'title' => (string) $bundle->name, 'price' => $price->money(), 'back' => route('catalog.index')];
        }

        abort_unless($type === ProductType::Course->value, 404);
        $course = Course::query()->find($id);
        abort_if($course === null, 404);
        $price = $offers->coursePrice($course, $market);
        abort_if($price === null, 404);

        return ['course' => $course, 'bundle' => null, 'plan' => null, 'title' => (string) $course->title, 'price' => $price, 'back' => PublicUrl::course($course, app()->getLocale())];
    }

    /**
     * The cover of each course line, keyed by order item id; bundles and plans have no cover and fall back to the placeholder.
     *
     * @return array<int, string|null>
     */
    private function covers(Order $order): array
    {
        $courseIds = $order->items->filter(static fn ($item): bool => $item->product_type === ProductType::Course)->pluck('product_id')->all();
        $courses = Course::query()->with('category')->whereIn('id', $courseIds)->get()->keyBy('id');
        $covers = [];
        foreach ($order->items as $item) {
            $course = $item->product_type === ProductType::Course ? $courses->get($item->product_id) : null;
            $covers[$item->getKey()] = $course instanceof Course ? $this->coverUrl($course) : null;
        }

        return $covers;
    }

    private function coverUrl(Course $course): ?string
    {
        $path = filled($course->cover_image) ? $course->cover_image
            : ($course->category?->status === 'active' && filled($course->category->image) ? $course->category->image : null);

        return $path === null ? null : Storage::disk('public')->url($path);
    }

    private function payer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->getAttribute('status') === 'active', 404);

        return $user;
    }

    private function owns(User $payer, Order $order): void
    {
        abort_unless((int) $order->payer_id === (int) $payer->getKey(), 404);
    }
}
