<x-layouts.base variant="public" :minimal="true" :title="__('errors.404_title')">
    <x-ui.card class="text-center">
        <p class="text-6xl font-extrabold text-primary" dir="ltr" aria-hidden="true">404</p>
        <h1 class="mt-2 text-2xl font-bold">{{ __('errors.404_title') }}</h1>
        <p class="mt-2 text-muted">{{ __('errors.404_text') }}</p>
        <p class="mt-2 text-sm text-muted">{{ __('errors.support') }}</p>
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            <x-ui.link :href="url('/')">{{ __('errors.home') }}</x-ui.link>
        </div>
    </x-ui.card>
</x-layouts.base>
