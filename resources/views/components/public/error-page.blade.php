@props(['status', 'retry' => false])
@php
    $locale = app()->getLocale();
    $support = config('app.support_email');
@endphp
<x-layouts.base variant="public" minimal :title="__('errors.'.$status.'_title')">
    <div class="grid min-h-[65vh] place-items-center">
        <x-ui.card class="w-full text-center shadow-card">
            <p class="mx-auto inline-flex rounded-full bg-assistant px-4 py-2 text-xl font-extrabold tabular-nums text-nav-active-text" dir="ltr" aria-hidden="true">{{ $status }}</p>
            <h1 class="mt-6 text-2xl font-extrabold sm:text-3xl">{{ __('errors.'.$status.'_title') }}</h1>
            <p class="mt-4 text-muted">{{ __('errors.'.$status.'_text') }}</p>
            <div class="mt-7 flex flex-wrap justify-center gap-2">
                @if($retry)<x-ui.button data-reload-page>{{ __('errors.retry') }}</x-ui.button>@endif
                <x-ui.button variant="secondary" data-history-back>{{ __('errors.go_back') }}</x-ui.button>
                <x-ui.link :href="\App\Domain\Seo\PublicUrl::home($locale)">{{ __('errors.home') }}</x-ui.link>
            </div>
            @if($support || in_array($status, [403, 404, 419, 429], true))
                <p class="mt-4 text-sm text-muted">{{ __('errors.support') }}</p>
                @if($support)
                    <x-ui.link :href="'mailto:'.$support" class="text-sm" dir="ltr">{{ $support }}</x-ui.link>
                @else
                    <x-ui.link :href="\App\Domain\Seo\PublicUrl::contact($locale)" class="text-sm">{{ __('public-site.contact') }}</x-ui.link>
                @endif
            @endif
        </x-ui.card>
    </div>
</x-layouts.base>
