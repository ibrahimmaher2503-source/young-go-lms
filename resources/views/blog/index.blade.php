@php
    $locale = app()->getLocale();
    $blogRoute = ['publicLocale' => $locale];
    $chip = 'inline-flex min-h-11 max-w-full items-center rounded-full border px-4 py-2 text-sm font-semibold wrap-anywhere hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
    $keep = array_filter(['q' => $filters['q'] ?? null, 'tag' => $filters['tag'] ?? null], filled(...));
    $keepCategory = array_filter(['q' => $filters['q'] ?? null, 'category' => $filters['category'] ?? null], filled(...));
@endphp
<x-layouts.base :title="$seo['title']" :locale-alternates="$seo['alternates']" variant="public" full indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="info-page" data-info-page="blog">
        <x-public.info-hero :title="__('blog.title')" :description="__('blog.description')" image="privacy"/>

        <x-public.info-banner quiet :title="__('blog.search')" image="images/public-info/about-band.webp" icon="search">
            <form method="get" action="{{ route('public.blog.index', $blogRoute) }}" class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]" role="search">
                @foreach(array_diff_key($filters, ['q' => 1]) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                <label for="blog-search" class="sr-only">{{ __('blog.search') }}</label>
                <x-ui.input id="blog-search" name="q" type="search" :placeholder="__('blog.search')" :value="$filters['q'] ?? ''" maxlength="160"/>
                <x-ui.button type="submit" class="w-full sm:w-auto">
                    <x-slot:icon><x-ui.icon name="search" class="size-4"/></x-slot:icon>
                    {{ __('blog.find') }}
                </x-ui.button>
            </form>
        </x-public.info-banner>

        @if($categories->isNotEmpty() || $tags->isNotEmpty() || $filters !== [])
        <div class="info-card space-y-4">
            @if($categories->isNotEmpty())
                <nav aria-label="{{ __('blog.categories') }}" class="flex flex-wrap gap-2">
                    <a href="{{ route('public.blog.index', $blogRoute + $keep) }}" @if(! isset($filters['category']))aria-current="true"@endif
                        class="{{ $chip }} {{ ! isset($filters['category']) ? 'border-primary bg-primary text-on-primary' : 'border-border bg-page text-body' }}">{{ __('blog.all_categories') }}</a>
                    @foreach($categories as $category)
                        @php($slug = \App\Domain\Content\Queries\PublicBlog::keyFor($category, $locale))
                        @php($active = in_array($filters['category'] ?? null, [$category->slug_ar, $category->slug_en], true))
                        <a href="{{ route('public.blog.index', $blogRoute + $keep + ['category' => $slug]) }}" @if($active)aria-current="true"@endif
                            class="{{ $chip }} {{ $active ? 'border-primary bg-primary text-on-primary' : 'border-border bg-page text-body' }}">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if($tags->isNotEmpty())
                <nav aria-label="{{ __('blog.tags') }}" class="flex flex-wrap items-center gap-2 border-t border-border pt-4">
                    <span class="text-sm font-semibold text-muted">{{ __('blog.tags') }}</span>
                    @foreach($tags as $tag)
                        @php($active = in_array($filters['tag'] ?? null, [$tag->slug_ar, $tag->slug_en], true))
                        <a href="{{ route('public.blog.index', $blogRoute + ($active ? $keepCategory : $keepCategory + ['tag' => \App\Domain\Content\Queries\PublicBlog::keyFor($tag, $locale)])) }}" @if($active)aria-current="true"@endif
                            class="{{ $chip }} {{ $active ? 'border-primary bg-primary text-on-primary' : 'border-border bg-page text-body' }}">{{ $tag->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if($filters !== [])
                <x-ui.link :href="route('public.blog.index', $blogRoute)" class="text-sm">{{ __('blog.clear') }}</x-ui.link>
            @endif
        </div>
        @endif

        <section aria-labelledby="blog-results" aria-describedby="blog-result-count" class="min-w-0 space-y-5">
            <h2 id="blog-results" @class(['sr-only' => $posts->isEmpty(), 'info-section-title' => $posts->isNotEmpty()])>{{ __('blog.posts') }}</h2>
            <p id="blog-result-count" class="sr-only">{{ __('blog.results', ['count' => $posts->total()]) }}</p>
            @if($posts->isEmpty())
                <div class="info-card"><x-ui.empty-state :title="__('blog.empty')" icon="book"/></div>
            @else
                <div class="public-card-grid grid gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($posts as $post)@include('blog._card', ['post' => $post])@endforeach
                </div>
                @if($posts->hasPages())<x-ui.pagination :paginator="$posts"/>@endif
            @endif
        </section>

        <x-public.info-banner :title="__('public-info.about.cta')" :description="__('public-info.about.cta_body')"
            image="images/public-info/catalog-band.webp">
            <x-public.info-course-link/>
        </x-public.info-banner>
    </div>
</x-layouts.base>
