{{-- Props: variant, minimal, parent. Cookie banner (first layer: accept and decline are equal) plus a persistent "Cookie settings" link, only on a marketing surface with tags configured. Plain forms: works without JS. --}}
@props(['variant' => 'public', 'minimal' => false, 'parent' => false])
@php $consent = app(\App\Http\Consent\ConsentSurface::class)->resolve($variant, $minimal, $parent); @endphp
@if($consent['active'])
    @if($consent['banner'])
        <section role="region" aria-labelledby="consent-title" data-consent-banner class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface p-4 shadow-lg">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4">
                <div class="min-w-0 flex-1 basis-80 space-y-1 text-start">
                    <h2 id="consent-title" class="font-bold">{{ __('consent.title') }}</h2>
                    <p>{{ __('consent.text') }} <x-ui.link :href="route('consent.show')">{{ __('consent.learn_more') }}</x-ui.link></p>
                    <p class="text-sm text-muted">{{ __('consent.draft_note') }}</p>
                </div>
                <form method="post" action="{{ route('consent.store') }}" class="flex flex-wrap gap-3">
                    @csrf
                    <x-ui.button type="submit" name="decision" value="decline" data-consent="decline">{{ __('consent.decline') }}</x-ui.button>
                    <x-ui.button type="submit" name="decision" value="accept" data-consent="accept">{{ __('consent.accept') }}</x-ui.button>
                </form>
            </div>
        </section>
    @endif
    <div class="mx-auto max-w-5xl px-4 pb-6 text-sm sm:px-8" data-consent-settings>
        <x-ui.link :href="route('consent.show')">{{ __('consent.settings_link') }}</x-ui.link>
    </div>
@endif
