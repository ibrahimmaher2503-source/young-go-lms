<x-layouts.base variant="public" indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>
    <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-8">
        <h1 class="text-3xl font-bold">{{ __('app.name') }}</h1>
        @foreach($blocks as $block)
            <section data-block-type="{{ $block->type }}" class="space-y-3">
                <h2 class="text-2xl font-semibold">{{ $block->title }}</h2>
                @if(filled($block->body))
                    <p class="whitespace-pre-line break-words">{{ $block->body }}</p>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.base>
