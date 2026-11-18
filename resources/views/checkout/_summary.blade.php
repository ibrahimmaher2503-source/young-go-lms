{{-- Props: lines (list of [title, learner|null, kind]), subtotal, discount (Money|null), total (Money), market. Read-only order summary shared by both checkout steps. --}}
<x-ui.card :title="__('checkout.summary')" class="space-y-4" data-checkout-summary>
    <ul class="space-y-3">
        @foreach($lines as $line)
            <li class="min-w-0">
                <p class="break-words font-semibold" dir="auto">{{ $line['title'] }}</p>
                <p class="text-sm text-muted">{{ __('checkout.kind_'.$line['kind']) }}@if($line['learner']) · {{ __('checkout.for_learner', ['name' => $line['learner']]) }}@endif</p>
            </li>
        @endforeach
    </ul>
    <dl class="space-y-2 border-t border-border pt-3">
        <div class="flex items-center justify-between gap-3"><dt class="text-muted">{{ __('checkout.subtotal') }}</dt><dd><x-ui.price :money="$subtotal" :market="$market" /></dd></div>
        @if($discount !== null)
            <div class="flex items-center justify-between gap-3" data-discount><dt class="text-muted">{{ __('checkout.discount') }}</dt><dd><bdi dir="ltr">-</bdi><x-ui.price :money="$discount" :market="$market" /></dd></div>
        @endif
        <div class="flex items-center justify-between gap-3 rounded-lg bg-page p-3 text-lg font-bold" data-total><dt>{{ __('checkout.total') }}</dt><dd><x-ui.price :money="$total" :market="$market" /></dd></div>
    </dl>
    <p class="text-sm text-muted">{{ __('checkout.access_note') }}</p>
</x-ui.card>
