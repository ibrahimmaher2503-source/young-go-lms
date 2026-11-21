@php($infoPage = in_array($page->seed_key, ['about', 'privacy', 'faq'], true))
<x-layouts.base :title="$page->title" variant="public" :indexable="isset($seo)" :locale-alternates="$seo['alternates'] ?? []" :full="$infoPage">
    <x-slot:head>@isset($seo)<x-seo.head :seo="$seo"/>@endisset</x-slot:head>

    @if($infoPage)
        @if($preview ?? false)
            <x-ui.alert tone="info" role="status" class="mb-4">{{ __('cms.preview_notice') }}</x-ui.alert>
        @endif
        @include('cms.'.$page->seed_key)
    @else
    <article @class([
        'public-page-stack mx-auto min-w-0',
        'max-w-3xl' => $page->template === 'policy',
        'max-w-4xl' => $page->template !== 'policy',
    ])>
        <x-ui.breadcrumb :items="[
            ['label' => __('public-site.home'), 'url' => \App\Domain\Seo\PublicUrl::home(app()->getLocale())],
            ['label' => $page->title],
        ]"/>

        @if($preview ?? false)
            <x-ui.alert tone="info" role="status">{{ __('cms.preview_notice') }}</x-ui.alert>
        @endif

        <div class="overflow-hidden rounded-card border border-border bg-surface">
            <header class="border-b border-border bg-assistant px-5 py-6 sm:px-8 sm:py-8">
                <h1 class="wrap-anywhere text-3xl font-extrabold leading-tight sm:text-4xl">{{ $page->title }}</h1>
            </header>
            <div class="px-5 py-6 sm:px-8 sm:py-8">
                <div class="max-w-prose space-y-6 leading-relaxed">
                    @foreach(preg_split('/\R{2,}/u', trim((string) $page->body)) ?: [] as $paragraph)
                        <p class="whitespace-pre-wrap wrap-anywhere" dir="auto">{{ $paragraph }}</p>
                    @endforeach
                </div>
                @if($page->seed_key === 'contact')
                    <div class="mt-6 flex flex-wrap gap-4 border-t border-border pt-4">
                        <x-ui.link :href="\App\Domain\Seo\PublicUrl::contact(app()->getLocale())">{{ __('public-site.contact') }}</x-ui.link>
                    </div>
                @endif
            </div>
        </div>
    </article>
    @endif
</x-layouts.base>
