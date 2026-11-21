<x-layouts.base variant="public" :title="__('contact.newsletter.title')">
    <div class="mx-auto max-w-xl">
        <section aria-labelledby="newsletter-heading" class="overflow-hidden rounded-xl border border-border bg-surface shadow-card" data-newsletter-card>
            <header class="border-b border-border bg-assistant px-5 py-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-lg border border-border bg-surface text-primary">
                    <x-ui.icon name="mail" class="size-6" />
                </span>
                <h1 id="newsletter-heading" class="text-3xl font-extrabold sm:text-4xl">{{ __('contact.newsletter.title') }}</h1>
                <p class="mt-4 text-muted">{{ __('contact.newsletter.intro') }}</p>
            </header>
            <div class="relative px-5 py-6 sm:p-8">
                <x-marketing.newsletter-form source="page" />
            </div>
        </section>
    </div>
</x-layouts.base>
