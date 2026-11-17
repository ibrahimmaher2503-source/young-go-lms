<x-layouts.base variant="public" :minimal="true" :title="__('errors.403_title')">
    <div class="grid min-h-[50vh] place-items-center"><x-ui.card class="w-full text-center">
        <p class="text-6xl font-extrabold text-primary" dir="ltr" aria-hidden="true">403</p>
        <h1 class="mt-2 text-2xl font-bold">{{ __('errors.403_title') }}</h1>
        <p class="mt-2 text-muted">{{ __('errors.403_text') }}</p>
        <p class="mt-2 text-sm text-muted">{{ __('errors.support') }}
            @if(config('app.support_email'))<a href="mailto:{{ config('app.support_email') }}" class="font-semibold text-primary underline" dir="ltr">{{ config('app.support_email') }}</a>@endif</p>
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            <x-ui.button variant="secondary" data-history-back>{{ __('errors.go_back') }}</x-ui.button>
            <x-ui.link :href="url('/')">{{ __('errors.home') }}</x-ui.link>
        </div>
    </x-ui.card></div>
</x-layouts.base>
