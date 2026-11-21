<x-layouts.base variant="public" :title="__('contact.unsubscribed.title')">
    <div class="mx-auto max-w-xl">
        <x-ui.card aria-labelledby="unsubscribe-heading" class="shadow-card">
            <span class="mb-4 grid size-12 place-items-center rounded-lg bg-success text-on-success">
                <x-ui.icon name="check" class="size-6" />
            </span>
            <h1 id="unsubscribe-heading" class="text-3xl font-extrabold sm:text-4xl">{{ __('contact.unsubscribed.title') }}</h1>
            <p class="mt-4 text-muted" role="status" data-unsubscribed>{{ __('contact.unsubscribed.done') }}</p>
            <div class="mt-7 border-t border-border pt-4">
                <x-ui.link :href="route('newsletter.show')">{{ __('contact.unsubscribed.resubscribe') }}</x-ui.link>
            </div>
        </x-ui.card>
    </div>
</x-layouts.base>
