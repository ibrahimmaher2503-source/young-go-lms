<x-layouts.base variant="public" :title="__('contact.newsletter.title')">
    <div class="mx-auto max-w-xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold">{{ __('contact.newsletter.title') }}</h1>
            <p class="text-muted">{{ __('contact.newsletter.intro') }}</p>
        </header>
        <x-ui.card class="relative" data-newsletter-card>
            <x-marketing.newsletter-form source="page" />
        </x-ui.card>
    </div>
</x-layouts.base>
