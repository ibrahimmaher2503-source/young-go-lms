@php
    $market = \App\Domain\Shared\Markets\Market::tryFrom($order->market) ?? \App\Domain\Shared\Markets\Market::Egypt;
    $field = fn (string $name) => $errors->first($name);
@endphp
<x-layouts.learner :title="__('manual_payment.title')">
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-2xl font-bold">{{ __('manual_payment.title') }}</h1>
        <x-ui.card>
            <p class="text-muted">{{ __('manual_payment.order') }}: <bdi dir="ltr">{{ $order->reference }}</bdi></p>
            <p class="mt-1 font-semibold">{{ __('manual_payment.amount_due') }}: <x-ui.price :money="$order->total()" :market="$market" /></p>
        </x-ui.card>

        @if(session('manual_payment_notice'))
            <x-ui.alert tone="success" role="status">{{ session('manual_payment_notice') }}</x-ui.alert>
        @endif

        @if($proof)
            <x-ui.alert :tone="['pending' => 'info', 'approved' => 'success', 'rejected' => 'danger'][$proof->status->value]">
                {{ __('manual_payment.status_'.$proof->status->value) }}
                @if($proof->status === \App\Domain\Commerce\ManualPaymentStatus::Rejected && $proof->review_note)
                    <span class="block mt-1" dir="auto">{{ __('manual_payment.rejected_reason') }}: {{ $proof->review_note }}</span>
                @endif
            </x-ui.alert>
        @endif

        @if($payable && (! $proof || $proof->status !== \App\Domain\Commerce\ManualPaymentStatus::Pending))
            <x-ui.card>
                <form method="post" action="{{ route('manual-payments.store', $order) }}" enctype="multipart/form-data" class="space-y-4" novalidate>
                    @csrf
                    <p class="text-muted">{{ __('manual_payment.intro') }}</p>
                    <x-ui.form-field for="payer_name" :label="__('manual_payment.payer_name')" :error="$field('payer_name')">
                        <x-ui.input id="payer_name" name="payer_name" :value="old('payer_name')" required maxlength="120" :invalid="$errors->has('payer_name')" />
                    </x-ui.form-field>
                    <x-ui.form-field for="sender_account" :label="__('manual_payment.sender_account')" :help="__('manual_payment.sender_help')" :error="$field('sender_account')">
                        <x-ui.input id="sender_account" name="sender_account" dir="ltr" :value="old('sender_account')" required maxlength="64" :invalid="$errors->has('sender_account')" />
                    </x-ui.form-field>
                    <x-ui.form-field for="transfer_reference" :label="__('manual_payment.reference')" :error="$field('transfer_reference')">
                        <x-ui.input id="transfer_reference" name="transfer_reference" dir="ltr" :value="old('transfer_reference')" required maxlength="64" :invalid="$errors->has('transfer_reference')" />
                    </x-ui.form-field>
                    <x-ui.form-field for="amount" :label="__('manual_payment.amount_sent', ['currency' => $order->currency])" :error="$field('amount')">
                        <x-ui.input id="amount" name="amount" dir="ltr" inputmode="decimal" :value="old('amount')" required :invalid="$errors->has('amount')" />
                    </x-ui.form-field>
                    <x-ui.form-field for="transferred_at" :label="__('manual_payment.transferred_at')" :error="$field('transferred_at')">
                        <x-ui.input id="transferred_at" type="datetime-local" name="transferred_at" dir="ltr" :value="old('transferred_at')" required :invalid="$errors->has('transferred_at')" />
                    </x-ui.form-field>
                    <x-ui.file-upload id="proof" name="proof" accept="image/png,image/jpeg,image/webp,application/pdf" :label="__('manual_payment.file')" :hint="__('manual_payment.file_hint', ['max' => $maxMegabytes])" required />
                    @if($errors->has('proof'))<p class="border-s-4 border-danger ps-2 text-sm font-semibold text-body">{{ $errors->first('proof') }}</p>@endif
                    @if($errors->has('order'))<x-ui.alert tone="danger" role="alert">{{ $errors->first('order') }}</x-ui.alert>@endif
                    <x-ui.button type="submit" variant="primary">{{ __('manual_payment.submit') }}</x-ui.button>
                </form>
            </x-ui.card>
        @elseif(! $payable)
            <x-ui.alert tone="warning">{{ __('manual_payment.order_not_payable') }}</x-ui.alert>
        @endif
    </div>
</x-layouts.learner>
