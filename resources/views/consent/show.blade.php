<x-layouts.base variant="public" :title="__('consent.page_title')">
    <div class="mx-auto max-w-2xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold">{{ __('consent.page_title') }}</h1>
            <p class="text-muted">{{ __('consent.page_intro') }}</p>
            <p class="text-sm text-muted">{{ __('consent.draft_note') }}</p>
        </header>
        <x-ui.card data-consent-preferences>
            @if($configured)
                <p class="mb-4" role="status" data-consent-state="{{ $consent->decided ? ($consent->accepted ? 'accepted' : 'declined') : 'none' }}">
                    {{ $consent->decided ? ($consent->accepted ? __('consent.state_accepted') : __('consent.state_declined')) : __('consent.state_none') }}
                </p>
                <form method="post" action="{{ route('consent.store') }}" class="flex flex-wrap gap-3">
                    @csrf
                    <x-ui.button type="submit" name="decision" value="decline">{{ __('consent.decline') }}</x-ui.button>
                    <x-ui.button type="submit" name="decision" value="accept">{{ __('consent.accept') }}</x-ui.button>
                </form>
            @else
                <p>{{ __('consent.none_used') }}</p>
            @endif
        </x-ui.card>
    </div>
</x-layouts.base>
