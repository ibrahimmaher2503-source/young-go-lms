<x-layouts.base variant="public" :title="__('contact.unsubscribed.title')">
    <div class="mx-auto max-w-xl space-y-4">
        <h1 class="text-3xl font-bold">{{ __('contact.unsubscribed.title') }}</h1>
        <x-ui.alert tone="success" role="status" data-unsubscribed>{{ __('contact.unsubscribed.done') }}</x-ui.alert>
        <p><x-ui.link :href="route('newsletter.show')">{{ __('contact.unsubscribed.resubscribe') }}</x-ui.link></p>
    </div>
</x-layouts.base>
