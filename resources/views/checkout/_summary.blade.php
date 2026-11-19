{{-- Props: lines (list of [title, learner|null, kind, cover (url|null, optional), format (recorded|live|hybrid, optional)]), subtotal, discount (Money|null), total (Money), market. Read-only order summary shared by both checkout steps. --}}
<x-ui.card :title="__('checkout.summary')" class="order-first space-y-4 lg:order-none lg:sticky lg:top-4 lg:self-start" data-checkout-summary>
    <ul class="space-y-3">
        @foreach($lines as $line)
            <li class="flex min-w-0 items-start gap-3" data-summary-line>
                <div class="size-16 shrink-0 overflow-hidden rounded-lg bg-assistant text-primary" data-summary-cover>
                    @if(! empty($line['cover']))
                        <img src="{{ $line['cover'] }}" alt="" width="64" height="64" @if($loop->first) loading="eager" fetchpriority="high" @else loading="lazy" @endif decoding="async" class="size-full object-cover">
                    @else
                        <span class="grid size-full place-items-center"><x-ui.icon :name="$line['kind'] === 'plan' ? 'award' : ($line['kind'] === 'bundle' ? 'layers' : 'book')" class="size-7"/></span>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="break-words font-semibold" dir="auto">{{ $line['title'] }}</p>
                    <p class="text-sm text-muted">{{ $line['kind'] === 'course' && ! empty($line['format']) ? __('checkout.kind_course_'.$line['format']) : __('checkout.kind_'.$line['kind']) }}@if($line['learner']) · {{ __('checkout.for_learner', ['name' => $line['learner']]) }}@endif</p>
                </div>
            </li>
        @endforeach
    </ul>
    <dl class="space-y-2 border-t border-border pt-3">
        <div class="flex items-center justify-between gap-3"><dt class="text-muted">{{ __('checkout.subtotal') }}</dt><dd><x-ui.price :money="$subtotal" :market="$market" /></dd></div>
        @if($discount !== null)
            <div class="flex items-center justify-between gap-3" data-discount><dt class="text-muted">{{ __('checkout.discount') }}</dt><dd><x-ui.price :money="$discount" :market="$market" negative /></dd></div>
        @endif
        <div class="flex items-center justify-between gap-3 rounded-lg bg-page p-3 text-lg font-bold" data-total><dt>{{ __('checkout.total') }}</dt><dd><x-ui.price :money="$total" :market="$market" /></dd></div>
    </dl>
    <p class="text-sm text-muted">{{ __('checkout.access_note') }}</p>
</x-ui.card>
