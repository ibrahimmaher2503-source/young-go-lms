<x-layouts.learner :title="__('pages.family_title')">
    <div class="mx-auto max-w-2xl">
        <x-ui.card class="text-center" data-family-unavailable>
            <h1 class="text-2xl font-bold">{{ __('pages.family_unavailable_title') }}</h1>
            <p class="mt-2 text-muted">{{ __('pages.family_unavailable_text') }}</p>
            <div class="mt-4 flex flex-wrap justify-center gap-4">
                <x-ui.link :href="url('/my')">{{ __('pages.nav_learning') }}</x-ui.link>
                <x-ui.link :href="url('/profile')">{{ __('pages.nav_profile') }}</x-ui.link>
            </div>
        </x-ui.card>
    </div>
</x-layouts.learner>
