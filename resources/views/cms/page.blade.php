<x-layouts.base :title="$page->title" variant="public" :indexable="isset($seo)">
    <x-slot:head>@isset($seo)<x-seo.head :seo="$seo"/>@endisset</x-slot:head>
    <article @class(['mx-auto space-y-4 px-4 py-8 sm:px-8', 'max-w-3xl' => $page->template === 'policy', 'max-w-5xl' => $page->template !== 'policy'])>
        @if($preview ?? false)
            <p role="status" class="font-semibold text-muted">{{ __('cms.preview_notice') }}</p>
        @endif
        <h1 class="text-3xl font-bold">{{ $page->title }}</h1>
        <p class="whitespace-pre-line break-words">{{ $page->body }}</p>
    </article>
</x-layouts.base>
