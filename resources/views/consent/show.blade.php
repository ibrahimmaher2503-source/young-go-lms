<x-layouts.base variant="public" :title="__('consent.page_title')">
    <div class="public-page-stack mx-auto max-w-2xl">
        <header class="rounded-xl border border-border bg-assistant p-5 sm:p-8">
            <span class="mb-4 grid size-12 place-items-center rounded-lg border border-border bg-surface text-primary">
                <x-ui.icon name="globe" class="size-6" />
            </span>
            <h1 id="cookie-settings-heading" class="text-3xl font-extrabold sm:text-4xl">{{ __('consent.page_title') }}</h1>
            @if($configured)
                <p class="mt-3 text-muted">{{ __('consent.page_intro') }}</p>
                <p class="mt-3 text-sm text-muted">{{ __('consent.draft_note') }}</p>
            @endif
        </header>

        <x-ui.card aria-labelledby="cookie-settings-heading" class="shadow-card" data-consent-preferences>
            @if($configured)
                <div class="mb-4">
                    <x-ui.alert :tone="$consent->decided && $consent->accepted ? 'success' : 'info'" role="status" id="cookie-settings-status" data-consent-state="{{ $consent->decided ? ($consent->accepted ? 'accepted' : 'declined') : 'none' }}">
                        {{ $consent->decided ? ($consent->accepted ? __('consent.state_accepted') : __('consent.state_declined')) : __('consent.state_none') }}
                    </x-ui.alert>
                </div>
                <form method="post" action="{{ route('consent.store') }}" aria-describedby="cookie-settings-status" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" name="decision" value="decline" class="hover:bg-hover">{{ __('consent.decline') }}</x-ui.button>
                    <x-ui.button type="submit" variant="secondary" name="decision" value="accept" class="hover:bg-hover">{{ __('consent.accept') }}</x-ui.button>
                </form>
            @else
                <p class="text-muted">{{ __('consent.none_used') }}</p>
            @endif
        </x-ui.card>
    </div>
</x-layouts.base>
