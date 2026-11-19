{{-- Props: cover (url|null), type (course|bundle|plan), id, title, price (Money), market, learners, owned (id => bool), selected, key, back, course|bundle. Step 1: choose who the purchase is for. --}}
@php
    $allOwned = $learners->isNotEmpty() && $learners->every(fn ($l) => ($owned[$l->getKey()] ?? false));
    $nameOf = fn ($u) => $u->display_name ?: $u->name;
    $lines = [['title' => $title, 'learner' => null, 'kind' => $type, 'cover' => $cover ?? null]];
@endphp
<x-layouts.learner :title="__('checkout.title')">
    <div class="mx-auto max-w-5xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ __('checkout.title') }}</h1>
            <p class="inline-flex items-center gap-2 text-sm text-muted"><x-ui.icon name="check" />{{ __('checkout.secure') }}</p>
        </div>
        <x-ui.stepper :steps="[__('checkout.step_learner'), __('checkout.step_pay'), __('checkout.step_done')]" :current="1" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0 space-y-4">
            <x-ui.card :title="__('checkout.learner_title')" data-checkout-learner>
                <form method="post" action="{{ route('checkout.start') }}" class="space-y-4" novalidate>
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="product_id" value="{{ $id }}">
                    <input type="hidden" name="key" value="{{ $key }}">
                    @if($learners->count() > 1)
                        <x-ui.form-field for="learner_id" :label="__('checkout.learner_label')" :help="__('checkout.learner_help')" :error="$errors->first('learner_id')">
                            <x-ui.select id="learner_id" name="learner_id" :invalid="$errors->has('learner_id')" aria-describedby="learner_id-help">
                                @foreach($learners as $learner)
                                    <option value="{{ $learner->getKey() }}" @selected($selected === $learner->getKey()) @disabled($owned[$learner->getKey()] ?? false)>{{ $nameOf($learner) }}@if($learner->getKey() === auth()->id()) ({{ __('checkout.myself') }})@endif @if($owned[$learner->getKey()] ?? false) - {{ __($type === 'plan' ? 'checkout.already_subscribed' : 'checkout.already_has') }}@endif</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.form-field>
                    @else
                        <input type="hidden" name="learner_id" value="{{ $learners->first()->getKey() }}">
                        <p>{{ __('checkout.learner_self', ['name' => $nameOf($learners->first())]) }}</p>
                    @endif

                    @if($allOwned)<x-ui.alert tone="info">{{ __($type === 'plan' ? 'checkout.all_subscribed' : 'checkout.all_enrolled') }}</x-ui.alert>@endif
                    @if($errors->has('order'))<x-ui.alert tone="danger" role="alert">{{ $errors->first('order') }}</x-ui.alert>@endif
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button type="submit" size="lg" :disabled="$allOwned">{{ __('checkout.continue') }}</x-ui.button>
                        <x-ui.link :href="$back">{{ __('checkout.back') }}</x-ui.link>
                    </div>
                </form>
            </x-ui.card>

            @if($benefits !== [])
                <x-ui.card :title="__('checkout.benefits_title')" data-checkout-benefits>
                    <ul class="grid gap-3 sm:grid-cols-2">
                        @foreach($benefits as $benefit)
                            <li class="flex min-w-0 items-start gap-3 rounded-lg bg-page p-3">
                                <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-assistant text-primary"><x-ui.icon :name="$benefit['icon']" class="size-4" /></span>
                                <span class="min-w-0 break-words pt-1" dir="auto">{{ $benefit['text'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
            </div>

            @include('checkout._summary', ['lines' => $lines, 'subtotal' => $price, 'discount' => null, 'total' => $price, 'market' => $market])
        </div>
    </div>
</x-layouts.learner>
