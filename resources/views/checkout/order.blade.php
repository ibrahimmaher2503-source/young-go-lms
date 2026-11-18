{{-- Props: order (items.learner loaded), payer, covers (item id => url|null), market, methods, coupon, proof, attempt, termsUrl, payee. Step 2/3 in the reference order: payer, payment method, coupon, terms, pay, then the status of the order. Every state comes from the stored order. --}}
@php
    use App\Domain\Commerce\ManualPaymentStatus;
    use App\Domain\Commerce\OrderStatus;
    use App\Domain\Commerce\PaymentAttemptStatus;
    use App\Domain\Shared\Money\Money;

    $locale = app()->getLocale();
    $titleKey = $locale === 'ar' ? 'title_ar' : 'title_en';
    $lines = $order->items->map(fn ($i) => [
        'title' => $i->{$titleKey} ?? $i->title_en ?? $i->title_ar,
        'learner' => $i->learner ? ($i->learner->display_name ?: $i->learner->name) : null,
        'kind' => $i->product_type->value,
        'cover' => $covers[$i->getKey()] ?? null,
    ])->all();
    $pending = $order->status === OrderStatus::Pending;
    $proofPending = $proof?->status === ManualPaymentStatus::Pending;
    $onlineOpen = $attempt !== null && $attempt->status->isOpen() && $attempt->expires_at->isFuture();
    $step = $pending ? 2 : 3;
    $manualOnly = $methods === [];
    $chosen = old('method', $methods[0]['key'] ?? 'manual');
    $learnerNames = collect($lines)->pluck('learner')->filter()->unique()->values();
    $transferTone = match ($proof?->status) {
        ManualPaymentStatus::Pending => 'info', ManualPaymentStatus::Approved => 'success', ManualPaymentStatus::Rejected => 'danger', default => 'neutral',
    };
    $transferKey = $proof === null ? 'none' : $proof->status->value;
    $tones = ['pending' => 'info', 'paid' => 'success', 'failed' => 'danger', 'cancelled' => 'neutral', 'refunded' => 'warning', 'partially_refunded' => 'warning', 'disputed' => 'danger'];
@endphp
<x-layouts.learner :title="__('checkout.title')">
    <div class="mx-auto max-w-5xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ __('checkout.title') }}</h1>
            <p class="text-sm text-muted">{{ __('checkout.order_ref') }}: <x-ui.ltr>{{ $order->reference }}</x-ui.ltr></p>
        </div>
        <x-ui.stepper :steps="[__('checkout.step_learner'), __('checkout.step_pay'), __('checkout.step_done')]" :current="$step" />

        @if(session('checkout_notice'))<x-ui.alert tone="success" role="status">{{ session('checkout_notice') }}</x-ui.alert>@endif

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0 space-y-4">
                @if($order->status === OrderStatus::Paid)
                    <x-ui.alert tone="success" role="status" data-status="paid">
                        <p class="font-semibold">{{ __('checkout.status_paid') }}</p>
                        <p class="mt-1">{{ __('checkout.status_paid_help') }}</p>
                    </x-ui.alert>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('learner.courses') }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 py-2 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('checkout.go_to_courses') }}</a>
                        <x-ui.link :href="route('payments.receipt', $order)">{{ __('checkout.receipt') }}</x-ui.link>
                    </div>
                @elseif(! $pending)
                    <x-ui.alert :tone="$order->status === OrderStatus::Failed ? 'danger' : 'warning'" role="status" data-status="{{ $order->status->value }}">
                        <p class="font-semibold">{{ __('checkout.status_'.$order->status->value) }}</p>
                        <p class="mt-1">{{ __('checkout.status_closed_help') }}</p>
                    </x-ui.alert>
                    <x-ui.link :href="route('catalog.index')">{{ __('checkout.browse') }}</x-ui.link>
                @else
                    <x-ui.card :title="__('checkout.payer_title')" data-payer-details>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div class="min-w-0"><dt class="text-sm text-muted">{{ __('checkout.payer_name') }}</dt><dd class="mt-1 min-h-11 break-words rounded-lg border border-border bg-page px-3 py-2.5 font-semibold" dir="auto">{{ $payer->name }}</dd></div>
                            <div class="min-w-0"><dt class="text-sm text-muted">{{ __('checkout.payer_email') }}</dt><dd class="mt-1 min-h-11 break-all rounded-lg border border-border bg-page px-3 py-2.5 font-semibold" dir="ltr">{{ $payer->email }}</dd></div>
                            @if($learnerNames->isNotEmpty())<div class="min-w-0 sm:col-span-2"><dt class="text-sm text-muted">{{ __('checkout.learner_for') }}</dt><dd class="mt-1 break-words font-semibold" dir="auto">{{ $learnerNames->implode(', ') }}</dd></div>@endif
                        </dl>
                        <p class="mt-3 text-sm text-muted">{{ __('checkout.payer_help') }}</p>
                    </x-ui.card>
                    @if($proofPending)
                        <x-ui.alert tone="info" role="status" data-status="proof-pending">
                            <p class="font-semibold">{{ __('checkout.status_proof_pending') }}</p>
                            <p class="mt-1">{{ __('checkout.status_proof_pending_help') }}</p>
                        </x-ui.alert>
                        <x-ui.link :href="route('manual-payments.create', $order)">{{ __('checkout.view_proof') }}</x-ui.link>
                    @else
                        @if($onlineOpen)
                            <x-ui.alert tone="info" role="status" data-status="awaiting-gateway">
                                <p class="font-semibold">{{ __('checkout.status_awaiting') }}</p>
                                <p class="mt-1">{{ __('checkout.status_awaiting_help') }}</p>
                                <a href="{{ route('checkout.order', $order) }}" class="mt-2 inline-flex min-h-11 items-center font-semibold underline underline-offset-4">{{ __('checkout.refresh_status') }}</a>
                            </x-ui.alert>
                        @elseif($attempt?->status === PaymentAttemptStatus::Failed || $attempt?->status === PaymentAttemptStatus::Expired)
                            <x-ui.alert tone="danger" role="status" data-status="attempt-failed">{{ __('checkout.status_attempt_failed') }}</x-ui.alert>
                        @endif
                        @if($proof?->status === ManualPaymentStatus::Rejected)
                            <x-ui.alert tone="warning" data-status="proof-rejected">{{ __('manual_payment.status_rejected') }}</x-ui.alert>
                        @endif

                        <form id="checkout-pay-form" method="post" action="{{ route('checkout.pay', $order) }}" novalidate data-checkout-pay>@csrf</form>

                        <x-ui.card :title="__('checkout.method_title')">
                            <fieldset class="space-y-3">
                                <legend class="sr-only">{{ __('checkout.method_title') }}</legend>
                                @foreach($methods as $method)
                                    <div class="rounded-lg border border-border p-2 has-[:checked]:border-primary has-[:checked]:bg-page">
                                        <x-ui.radio form="checkout-pay-form" :id="'method-'.$method['key']" name="method" :value="$method['key']" :label="$method['label']" :checked="$chosen === $method['key']" />
                                    </div>
                                @endforeach
                                <div class="rounded-lg border border-border p-2 has-[:checked]:border-primary has-[:checked]:bg-page">
                                    <x-ui.radio form="checkout-pay-form" id="method-manual" name="method" value="manual" :label="__('checkout.method_manual')" :checked="$chosen === 'manual'" />
                                </div>
                            </fieldset>
                            @if($manualOnly)<x-ui.alert tone="info" class="mt-3" data-online-unavailable>{{ __('payments.gateway_unavailable') }}</x-ui.alert>@endif
                            <p class="mt-3 flex items-start gap-2 text-sm text-muted"><x-ui.icon name="help" class="mt-0.5" />{{ __('checkout.method_online_help') }}</p>
                            @if($errors->has('method'))<p class="mt-2 border-s-4 border-danger ps-2 text-sm font-semibold text-body">{{ $errors->first('method') }}</p>@endif
                        </x-ui.card>

                        <x-ui.card :title="__('checkout.manual_title')" data-checkout-manual>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-muted">{{ __('checkout.manual_help') }}</p>
                                <span class="inline-flex items-center gap-2 text-sm text-muted">{{ __('checkout.transfer_status') }}: <x-ui.badge :tone="$transferTone" data-transfer-status="{{ $transferKey }}">{{ __('checkout.transfer_status_'.$transferKey) }}</x-ui.badge></span>
                            </div>
                            <p class="mt-2" dir="auto">{{ $payee ?? __('checkout.manual_payee_pending') }}</p>
                            <p class="mt-3 text-sm text-muted">{{ __('checkout.transfer_reference') }}: <bdi dir="ltr" class="font-semibold text-body">{{ $order->reference }}</bdi></p>
                            <a href="{{ route('manual-payments.create', $order) }}" class="mt-3 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 font-semibold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" data-transfer-upload><x-ui.icon name="download" class="rotate-180" />{{ __('checkout.transfer_upload') }}</a>
                        </x-ui.card>

                        <x-ui.card :title="__('checkout.coupon_title')" data-checkout-coupon>
                            @if($coupon)
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p>{{ __('checkout.coupon_active') }}: <bdi dir="ltr" class="font-semibold">{{ $coupon->code }}</bdi></p>
                                    <form method="post" action="{{ route('checkout.coupon.remove', $order) }}">
                                        @csrf @method('DELETE')
                                        <x-ui.button type="submit" variant="secondary">{{ __('checkout.coupon_remove') }}</x-ui.button>
                                    </form>
                                </div>
                            @else
                                <form method="post" action="{{ route('checkout.coupon.apply', $order) }}" class="space-y-2" novalidate>
                                    @csrf
                                    <x-ui.form-field for="code" :label="__('checkout.coupon_label')" :error="$errors->first('code') ?: $errors->first('coupon')">
                                        <div class="flex flex-wrap gap-2">
                                            <x-ui.input id="code" name="code" dir="ltr" class="min-w-0 flex-1" maxlength="64" autocomplete="off" :value="old('code')" :invalid="$errors->has('code') || $errors->has('coupon')" />
                                            <x-ui.button type="submit" variant="secondary">{{ __('checkout.coupon_apply') }}</x-ui.button>
                                        </div>
                                    </x-ui.form-field>
                                </form>
                            @endif
                        </x-ui.card>

                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-x-2">
                                <x-ui.checkbox form="checkout-pay-form" id="terms" name="terms" value="1" :label="__('checkout.terms_label')" :checked="old('terms') === '1'" aria-describedby="terms-error" />
                                @if($termsUrl)<x-ui.link :href="$termsUrl" target="_blank" rel="noopener">{{ __('checkout.terms_link') }}</x-ui.link>@endif
                            </div>
                            @if($errors->has('terms'))<p id="terms-error" class="border-s-4 border-danger ps-2 text-sm font-semibold text-body">{{ $errors->first('terms') }}</p>@endif
                            @foreach(['order', 'payment'] as $bag)
                                @if($errors->has($bag))<x-ui.alert tone="danger" role="alert">{{ $errors->first($bag) }}</x-ui.alert>@endif
                            @endforeach
                            <x-ui.button form="checkout-pay-form" type="submit" size="lg" class="w-full">{{ __('checkout.pay') }}</x-ui.button>
                        </div>
                    @endif
                @endif
            </div>

            @include('checkout._summary', [
                'lines' => $lines, 'subtotal' => Money::ofMinor($order->subtotal_minor, $order->currency),
                'discount' => $order->discount_minor > 0 ? Money::ofMinor($order->discount_minor, $order->currency) : null,
                'total' => $order->total(), 'market' => $market,
            ])
        </div>
    </div>
</x-layouts.learner>
