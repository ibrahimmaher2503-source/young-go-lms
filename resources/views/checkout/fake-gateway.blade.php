{{-- Props: attempt, market, money, orderReference. Local and testing only: the demo stand-in for a hosted payment page. --}}
<x-layouts.base :title="__('fake_gateway.title')" variant="public">
    <div class="mx-auto max-w-md space-y-4" data-fake-gateway>
        <x-ui.alert tone="warning">{{ __('fake_gateway.notice') }}</x-ui.alert>
        <x-ui.card :title="__('fake_gateway.title')">
            <p>{{ __('fake_gateway.order') }}: <bdi dir="ltr">{{ $orderReference }}</bdi></p>
            <p class="text-xl font-bold"><x-ui.price :money="$money" :market="$market" /></p>
            <form method="post" action="{{ route('fake-gateway.complete', ['reference' => $attempt->reference]) }}" class="mt-4 flex flex-wrap gap-3">
                @csrf
                <x-ui.button type="submit" name="outcome" value="pay" data-fake-pay>{{ __('fake_gateway.pay') }}</x-ui.button>
                <x-ui.button type="submit" name="outcome" value="fail" variant="secondary" data-fake-fail>{{ __('fake_gateway.fail') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.base>
