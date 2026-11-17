@php
    $locale = app()->getLocale();
    $chip = 'inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
    $keep = array_filter(['q' => $filters['q'] ?? null, 'tag' => $filters['tag'] ?? null]);
    $keepCategory = array_filter(['q' => $filters['q'] ?? null, 'category' => $filters['category'] ?? null]);
@endphp
<x-layouts.base :title="$seo['title']" variant="public" indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>
    <div class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold">{{ __('blog.title') }}</h1>
            <p class="break-words">{{ __('blog.description') }}</p>
        </header>

        <form method="get" action="{{ route('blog.index') }}" class="flex flex-wrap items-end gap-3" role="search">
            @foreach(array_diff_key($filters, ['q' => 1]) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
            <div class="min-w-0 flex-1">
                <label for="blog-search" class="mb-1 block font-semibold">{{ __('blog.search') }}</label>
                <x-ui.input id="blog-search" name="q" type="search" :value="$filters['q'] ?? ''" maxlength="160"/>
            </div>
            <x-ui.button type="submit">{{ __('blog.find') }}</x-ui.button>
        </form>

        @if($categories->isNotEmpty())
            <nav aria-label="{{ __('blog.categories') }}" class="flex flex-wrap gap-2">
                <a href="{{ route('blog.index', array_filter(['q' => $filters['q'] ?? null, 'tag' => $filters['tag'] ?? null])) }}" @if(! isset($filters['category']))aria-current="true"@endif
                    class="{{ $chip }} {{ ! isset($filters['category']) ? 'border-primary bg-primary text-on-primary' : 'border-border bg-surface text-body' }}">{{ __('blog.all_categories') }}</a>
                @foreach($categories as $category)
                    @php($slug = \App\Domain\Content\Queries\PublicBlog::keyFor($category, $locale))
                    @php($active = in_array($filters['category'] ?? null, [$category->slug_ar, $category->slug_en], true))
                    <a href="{{ route('blog.index', $keep + ['category' => $slug]) }}" @if($active)aria-current="true"@endif
                        class="{{ $chip }} {{ $active ? 'border-primary bg-primary text-on-primary' : 'border-border bg-surface text-body' }}">{{ $category->name }}</a>
                @endforeach
            </nav>
        @endif

        @if($tags->isNotEmpty())
            <nav aria-label="{{ __('blog.tags') }}" class="flex flex-wrap items-center gap-2">
                <span class="font-semibold">{{ __('blog.tags') }}</span>
                @foreach($tags as $tag)
                    @php($active = in_array($filters['tag'] ?? null, [$tag->slug_ar, $tag->slug_en], true))
                    <a href="{{ route('blog.index', $active ? $keepCategory : $keepCategory + ['tag' => \App\Domain\Content\Queries\PublicBlog::keyFor($tag, $locale)]) }}" @if($active)aria-current="true"@endif
                        class="{{ $chip }} {{ $active ? 'border-primary bg-primary text-on-primary' : 'border-border bg-surface text-body' }}">{{ $tag->name }}</a>
                @endforeach
            </nav>
        @endif

        @if($filters !== [])
            <p><x-ui.link :href="route('blog.index')">{{ __('blog.clear') }}</x-ui.link></p>
        @endif

        <section aria-labelledby="blog-results" class="min-w-0 space-y-4">
            <h2 id="blog-results" class="sr-only">{{ __('blog.results', ['count' => $posts->total()]) }}</h2>
            @if($posts->isEmpty())
                <x-ui.card><p>{{ __('blog.empty') }}</p></x-ui.card>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($posts as $post)@include('blog._card', ['post' => $post])@endforeach
                </div>
                @if($posts->hasPages())<x-ui.pagination :paginator="$posts"/>@endif
            @endif
        </section>
    </div>
</x-layouts.base>
