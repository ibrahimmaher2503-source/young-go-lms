{{-- Props: subscriptions (Subscription with plan.prices, learner, periods), market. The account's own subscriptions as learner or payer. --}}
@php
    $tones = ['active' => 'success', 'grace' => 'warning', 'cancelled' => 'neutral', 'expired' => 'danger'];
    $date = fn ($d) => $d?->copy()->locale(app()->getLocale())->isoFormat('LL');
@endphp
<x-layouts.learner :title="__('subscriptions.my_title')">
    <div class="mx-auto max-w-4xl space-y-4">
        <h1 class="text-2xl font-bold">{{ __('subscriptions.my_title') }}</h1>
        <p class="text-muted">{{ __('subscriptions.my_intro') }}</p>

        @if(session('subscription_notice'))<x-ui.alert tone="success" role="status">{{ session('subscription_notice') }}</x-ui.alert>@endif
        @foreach(['order', 'subscription'] as $field)
            @if($errors->has($field))<x-ui.alert tone="danger" role="alert">{{ $errors->first($field) }}</x-ui.alert>@endif
        @endforeach

        @if($subscriptions->isEmpty())
            <x-ui.empty-state :title="__('subscriptions.my_empty')" :description="__('subscriptions.my_empty_help')" data-empty>
                @if(\Illuminate\Support\Facades\Route::has('plans.index'))<x-ui.link :href="route('plans.index')">{{ __('subscriptions.browse_plans') }}</x-ui.link>@endif
            </x-ui.empty-state>
        @else
            @foreach($subscriptions as $subscription)
                @php
                    $state = $subscription->displayState();
                    $latest = $subscription->latestPeriod();
                    $price = $subscription->plan?->priceFor($market);
                    $opens = $subscription->renewalOpensAt();
                    $renewable = $opens === null || $opens->lte(now());
                    $nameOf = $subscription->learner?->display_name ?: $subscription->learner?->name;
                    $included = $subscription->plan === null ? collect() : $subscription->plan->courses->map(fn ($row) => $row->course)->filter(fn ($course) => $course?->status === \App\Domain\Catalog\CourseStatus::Published)->values();
                    $nextRenewal = in_array($state, ['active', 'grace'], true) ? $latest?->ends_at : null;
                @endphp
                <x-ui.card data-subscription="{{ $subscription->getKey() }}" data-state="{{ $state }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="break-words text-lg font-semibold" dir="auto">{{ $subscription->plan?->name }}</p>
                            @if($subscription->learner_id !== auth()->id())
                                <p class="text-sm text-muted">{{ __('subscriptions.subscriber') }}: <bdi dir="auto">{{ $nameOf }}</bdi></p>
                            @endif
                        </div>
                        <x-ui.badge :tone="$tones[$state] ?? 'neutral'">{{ __('subscriptions.state_'.$state) }}</x-ui.badge>
                    </div>

                    @if($latest)
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                            <div><dt class="text-muted">{{ __('subscriptions.paid_from') }}</dt><dd class="font-semibold">{{ $date($latest->starts_at) }}</dd></div>
                            <div><dt class="text-muted">{{ __('subscriptions.period_end') }}</dt><dd class="font-semibold">{{ $date($latest->ends_at) }}</dd></div>
                            <div><dt class="text-muted">{{ __('subscriptions.grace_until') }}</dt><dd class="font-semibold">{{ $date($latest->grace_ends_at) }}</dd></div>
                        </dl>
                    @endif
                    @if($nextRenewal)
                        <p class="mt-3 flex flex-wrap items-center gap-x-2 rounded-lg bg-page px-3 py-2 text-sm" data-next-renewal><x-ui.icon name="calendar" class="size-4 text-primary" /><span class="text-muted">{{ __('subscriptions.next_renewal') }}</span> <strong>{{ $date($nextRenewal) }}</strong></p>
                    @endif
                    @if($included->isNotEmpty())
                        <div class="mt-4" data-plan-benefits>
                            <h3 class="font-semibold">{{ __('subscriptions.benefits_title', ['count' => $included->count()]) }}</h3>
                            <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach($included as $course)
                                    <li class="flex min-w-0 items-center gap-2">
                                        <span class="grid size-5 shrink-0 place-items-center rounded-full bg-success text-on-success" aria-hidden="true"><x-ui.icon name="check" class="size-3" /></span>
                                        <x-ui.link :href="route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)])" class="min-w-0 break-words" dir="auto">{{ $course->title }}</x-ui.link>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($price)
                        <p class="mt-2 text-sm text-muted">{{ __('subscriptions.price_column') }}: <x-ui.price :money="\App\Domain\Shared\Money\Money::ofMinor($price->amount_minor, $price->currency)" :market="$market" /> / {{ __('subscriptions.term_'.$subscription->plan->term->value) }}</p>
                    @endif
                    @if($state === 'cancelled')<p class="mt-2 text-sm">{{ __('subscriptions.cancelled_note') }}</p>@endif
                    @if($state === 'expired')<p class="mt-2 text-sm">{{ __('subscriptions.expired_note') }}</p>@endif

                    <div class="mt-3 flex flex-wrap items-start gap-3">
                        @if($renewable)
                            <form method="post" action="{{ route('subscription.renew', $subscription->getKey()) }}">
                                @csrf
                                <x-ui.button type="submit" data-renew>{{ __('subscriptions.renew') }}</x-ui.button>
                            </form>
                        @else
                            <p class="text-sm text-muted" data-renew-closed>{{ __('subscriptions.renew_opens', ['date' => $date($opens)]) }}</p>
                        @endif
                        @if($state === 'active' || $state === 'grace')
                            <form method="post" action="{{ route('subscription.cancel', $subscription->getKey()) }}" class="space-y-1">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" data-cancel>{{ __('subscriptions.cancel') }}</x-ui.button>
                                <p class="text-sm text-muted">{{ __('subscriptions.cancel_confirm') }}</p>
                            </form>
                        @endif
                    </div>
                    @if($renewable)<p class="mt-2 text-sm text-muted">{{ __('subscriptions.renew_help') }}</p>@endif
                </x-ui.card>
            @endforeach
        @endif

        <p><x-ui.link :href="route('payments.history')">{{ __('subscriptions.my_payments_link') }}</x-ui.link></p>
    </div>
</x-layouts.learner>
