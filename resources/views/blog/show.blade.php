@php
    $locale = app()->getLocale();
    $category = $post->category?->status === \App\Models\BlogCategory::ACTIVE ? $post->category : null;
    $author = $post->author?->display_name ?: $post->author?->name;
@endphp
<x-layouts.base :title="$seo['title']" variant="public" indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>
    <article class="mx-auto max-w-3xl space-y-6" data-blog-post>
        <x-ui.breadcrumb :items="[
            ['label' => __('app.name'), 'url' => url('/')],
            ['label' => __('blog.title'), 'url' => route('blog.index')],
            ['label' => $post->title, 'url' => '#'],
        ]"/>
        <header class="space-y-3">
            <h1 class="break-words text-3xl font-bold">{{ $post->title }}</h1>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                @if($category)<x-ui.badge>{{ $category->name }}</x-ui.badge>@endif
                <time datetime="{{ $post->published_at->toAtomString() }}">{{ __('blog.published_on', ['date' => $post->published_at->locale($locale)->isoFormat('LL')]) }}</time>
                @if(filled($author))<span>{{ __('blog.by', ['name' => $author]) }}</span>@endif
            </p>
        </header>
        @if(filled($post->featured_image))
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($post->featured_image) }}" alt="{{ $post->getTranslation('featured_image_alt', $locale, false) }}"
                width="1280" height="720" loading="eager" fetchpriority="high" decoding="async" class="aspect-video w-full rounded-xl object-cover">
        @endif
        @if(filled($post->excerpt))<p class="break-words text-lg font-semibold">{{ $post->excerpt }}</p>@endif
        <div class="whitespace-pre-line break-words" dir="auto">{{ $post->body }}</div>
        @if($post->tags->isNotEmpty())
            <ul class="flex flex-wrap gap-2" aria-label="{{ __('blog.tags') }}">
                @foreach($post->tags as $tag)
                    <li><a href="{{ route('blog.index', ['tag' => \App\Domain\Content\Queries\PublicBlog::keyFor($tag, $locale)]) }}"
                        class="inline-flex min-h-11 items-center rounded-full border border-border bg-surface px-4 text-sm font-semibold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ $tag->name }}</a></li>
                @endforeach
            </ul>
        @endif
        @if($related->isNotEmpty())
            <section aria-labelledby="blog-related" class="space-y-4 border-t border-border pt-6">
                <h2 id="blog-related" class="text-xl font-bold">{{ __('blog.related') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related as $item)@include('blog._card', ['post' => $item])@endforeach
                </div>
            </section>
        @endif
        <p><x-ui.link :href="route('blog.index')">{{ __('blog.back') }}</x-ui.link></p>
    </article>
</x-layouts.base>
