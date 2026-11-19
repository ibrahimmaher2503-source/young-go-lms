{{-- Props: attempt, market, money, orderReference. Local and testing only: the demo stand-in for a hosted payment page, styled like the checkout cards. --}}
<x-layouts.base :title="__('fake_gateway.title')" variant="public">
    <div class="mx-auto max-w-2xl space-y-4" data-fake-gateway>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ __('fake_gateway.title') }}</h1>
            <p class="inline-flex items-center gap-2 text-sm text-muted"><x-ui.icon name="check" />{{ __('checkout.secure') }}</p>
        </div>
        <x-ui.alert tone="warning">{{ __('fake_gateway.notice') }}</x-ui.alert>
        <x-ui.card>
            <dl class="space-y-2">
                <div class="flex items-center justify-between gap-3"><dt class="text-muted">{{ __('fake_gateway.order') }}</dt><dd class="min-w-0 break-words font-semibold"><x-ui.ltr>{{ $orderReference }}</x-ui.ltr></dd></div>
                <div class="flex items-center justify-between gap-3 rounded-lg bg-page p-3 text-lg font-bold" data-total><dt>{{ __('checkout.total') }}</dt><dd><x-ui.price :money="$money" :market="$market" /></dd></div>
            </dl>
            <form method="post" action="{{ route('fake-gateway.complete', ['reference' => $attempt->reference]) }}" class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                @csrf
                <div class="space-y-2">
                    <x-ui.button type="submit" size="lg" name="outcome" value="pay" class="w-full" data-fake-pay>{{ __('fake_gateway.pay') }}</x-ui.button>
                    <p class="text-sm text-muted">{{ __('fake_gateway.pay_hint') }}</p>
                </div>
                <div class="space-y-2">
                    <x-ui.button type="submit" size="lg" name="outcome" value="fail" variant="secondary" class="w-full" data-fake-fail>{{ __('fake_gateway.fail') }}</x-ui.button>
                    <p class="text-sm text-muted">{{ __('fake_gateway.fail_hint') }}</p>
                </div>
            </form>
        </x-ui.card>
        <x-ui.card :title="__('fake_gateway.steps_title')" data-fake-steps>
            <ol class="space-y-3">
                @foreach(['step_1', 'step_2', 'step_3'] as $index => $step)
                    <li class="flex items-start gap-3">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-assistant font-bold text-primary" aria-hidden="true"><bdi dir="ltr">{{ $index + 1 }}</bdi></span>
                        <span class="min-w-0 break-words pt-1">{{ __('fake_gateway.'.$step) }}</span>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    </div>
</x-layouts.base>
