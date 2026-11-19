@php
    use App\Domain\Commerce\OrderStatus;
    use App\Domain\Commerce\Queries\PaymentHistory;
    use App\Domain\Commerce\RefundStatus;
    use App\Domain\Shared\Markets\Market;
    use App\Domain\Shared\Money\Money;

    $tones = ['pending' => 'info', 'paid' => 'success', 'failed' => 'danger', 'cancelled' => 'neutral', 'refunded' => 'warning', 'partially_refunded' => 'warning', 'disputed' => 'danger'];
@endphp
<x-layouts.learner :title="__('refunds.history_title')">
    <div class="mx-auto max-w-4xl space-y-4">
        <h1 class="text-2xl font-bold">{{ __('refunds.history_title') }}</h1>
        <p class="text-muted">{{ __('refunds.history_intro') }}</p>
        <p><x-ui.link :href="route('subscription.index')">{{ __('subscriptions.my_title') }}</x-ui.link></p>

        @if($summary['orders'] > 0)
            <dl class="grid gap-3 sm:grid-cols-3" data-payments-summary>
                <div class="rounded-xl border border-border bg-surface p-4"><dt class="text-sm text-muted">{{ __('refunds.sum_orders') }}</dt><dd class="mt-1 text-xl font-bold"><bdi dir="ltr">{{ number_format($summary['orders']) }}</bdi></dd></div>
                <div class="rounded-xl border border-border bg-surface p-4"><dt class="text-sm text-muted">{{ __('refunds.sum_paid') }}</dt><dd class="mt-1 text-xl font-bold">
                    @forelse($summary['paid'] as $currency => $minor)
                        <span class="block"><x-ui.price :money="Money::ofMinor($minor, $currency)" :market="Market::Egypt" /></span>
                    @empty
                        <span class="text-base font-semibold text-muted">{{ __('refunds.sum_none') }}</span>
                    @endforelse
                </dd></div>
                <div class="rounded-xl border border-border bg-surface p-4"><dt class="text-sm text-muted">{{ __('refunds.sum_last') }}</dt><dd class="mt-1 text-xl font-bold">
                    @if($summary['last_paid_at'])<time datetime="{{ $summary['last_paid_at']->toIso8601String() }}">{{ $summary['last_paid_at']->locale(app()->getLocale())->isoFormat('LL') }}</time>@else<span class="text-base font-semibold text-muted">{{ __('refunds.sum_none') }}</span>@endif
                </dd></div>
            </dl>

            @php
                $chip = 'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
            @endphp
            <nav aria-label="{{ __('refunds.filter_label') }}" class="flex flex-wrap gap-2" data-payments-filter>
                <a href="{{ route('payments.history') }}" @if(! $status) aria-current="true" @endif class="{{ $chip }} {{ ! $status ? 'border-primary bg-primary text-on-primary' : 'border-border bg-surface text-body' }}">{{ __('refunds.filter_all') }} <bdi dir="ltr">{{ $summary['orders'] }}</bdi></a>
                @foreach(OrderStatus::cases() as $case)
                    @continue(($summary['counts'][$case->value] ?? 0) === 0)
                    <a href="{{ route('payments.history', ['status' => $case->value]) }}" @if($status === $case) aria-current="true" @endif class="{{ $chip }} {{ $status === $case ? 'border-primary bg-primary text-on-primary' : 'border-border bg-surface text-body' }}">{{ __('refunds.order_status_'.$case->value) }} <bdi dir="ltr">{{ $summary['counts'][$case->value] }}</bdi></a>
                @endforeach
            </nav>
        @endif

        @if($orders->isEmpty() && $status)
            <x-ui.empty-state :title="__('refunds.filter_empty')" data-empty-filter>
                <x-ui.link :href="route('payments.history')">{{ __('refunds.filter_all') }}</x-ui.link>
            </x-ui.empty-state>
        @elseif($orders->isEmpty())
            <x-ui.empty-state :title="__('refunds.history_empty')" :description="__('refunds.history_empty_help')" data-empty>
                <x-ui.link :href="route('catalog.index')">{{ __('learner.browse') }}</x-ui.link>
            </x-ui.empty-state>
        @else
            @foreach($orders as $order)
                @php($market = Market::tryFrom($order->market) ?? Market::Egypt)
                @php($titleKey = app()->getLocale() === 'ar' ? 'title_ar' : 'title_en')
                <x-ui.card data-order="{{ $order->reference }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold"><x-ui.ltr>{{ $order->reference }}</x-ui.ltr></p>
                            <p class="text-sm text-muted">{{ $order->created_at?->locale(app()->getLocale())->isoFormat('LL') }}</p>
                        </div>
                        <x-ui.badge :tone="$tones[$order->status->value] ?? 'neutral'">{{ __('refunds.order_status_'.$order->status->value) }}</x-ui.badge>
                    </div>

                    <ul class="mt-3 space-y-1">
                        @foreach($order->items as $item)
                            <li dir="auto">{{ $item->{$titleKey} ?? $item->title_en ?? $item->title_ar }}</li>
                        @endforeach
                    </ul>

                    <p class="mt-3 font-semibold">{{ __('refunds.total') }}: <x-ui.price :money="$order->total()" :market="$market" /></p>

                    @foreach($order->refunds as $refund)
                        <div class="mt-2 border-s-4 border-warning ps-2 text-sm" data-refund>
                            @if($refund->status === RefundStatus::Executed)
                                {{ __('refunds.refunded_amount') }}: <x-ui.price :money="$refund->amount()" :market="$market" />
                                <x-ui.link :href="route('payments.refund-receipt', ['order' => $order, 'refund' => $refund])">{{ __('refunds.download_refund_receipt') }}</x-ui.link>
                            @else
                                {{ __('refunds.refund_in_review') }}: <x-ui.price :money="$refund->amount()" :market="$market" />
                            @endif
                        </div>
                    @endforeach

                    <div class="mt-3 flex flex-wrap gap-3">
                        @if(PaymentHistory::hasReceipt($order))
                            <x-ui.link :href="route('payments.receipt', $order)" data-receipt>{{ __('refunds.download_receipt') }}</x-ui.link>
                        @endif
                        @if($order->status === \App\Domain\Commerce\OrderStatus::Pending && $order->total_minor > 0)
                            <x-ui.link :href="route('manual-payments.create', $order)">{{ __('refunds.send_proof') }}</x-ui.link>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
            @if($orders->hasPages())<x-ui.pagination :paginator="$orders"/>@endif
        @endif
    </div>
</x-layouts.learner>
