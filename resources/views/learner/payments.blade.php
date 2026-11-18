@php
    use App\Domain\Commerce\Queries\PaymentHistory;
    use App\Domain\Commerce\RefundStatus;
    use App\Domain\Shared\Markets\Market;
    use App\Domain\Shared\Money\Money;

    $tones = ['pending' => 'info', 'paid' => 'success', 'failed' => 'danger', 'cancelled' => 'neutral', 'refunded' => 'warning', 'partially_refunded' => 'warning', 'disputed' => 'danger'];
@endphp
<x-layouts.learner :title="__('refunds.history_title')">
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-2xl font-bold">{{ __('refunds.history_title') }}</h1>
        <p class="text-muted">{{ __('refunds.history_intro') }}</p>
        <p><x-ui.link :href="route('subscription.index')">{{ __('subscriptions.my_title') }}</x-ui.link></p>

        @if($orders->isEmpty())
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
                            <p class="font-semibold"><bdi dir="ltr">{{ $order->reference }}</bdi></p>
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
