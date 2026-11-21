@php
    $locale = app()->getLocale();
    $blog = \App\Domain\Seo\PublicUrl::blog($locale);
    $category = $post->category?->status === \App\Models\BlogCategory::ACTIVE ? $post->category : null;
    $author = $post->author?->display_name ?: $post->author?->name;
    $cover = filled($post->featured_image) ? Illuminate\Support\Facades\Storage::disk('public')->url($post->featured_image) : null;
@endphp
<x-layouts.base :title="$seo['title']" :locale-alternates="$seo['alternates']" variant="public" full indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <article class="info-page" data-info-page="blog-post" aria-labelledby="info-page-title" data-blog-post>
        <x-public.info-hero :title="$post->title" :description="$post->excerpt" image="privacy"
            :parent="['label' => __('blog.title'), 'url' => $blog]" :cover="$cover" :cover-alt="$post->getTranslation('featured_image_alt', $locale, false)"/>

        <div class="info-card mx-auto w-full max-w-4xl space-y-5">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
                @if($category)<x-ui.badge>{{ $category->name }}</x-ui.badge>@endif
                <span class="inline-flex items-center gap-2"><x-ui.icon name="calendar" class="size-4"/><time datetime="{{ $post->published_at->toAtomString() }}">{{ __('blog.published_on', ['date' => $post->published_at->locale($locale)->isoFormat('LL')]) }}</time></span>
                @if(filled($author))<span class="inline-flex items-center gap-2 wrap-anywhere"><x-ui.icon name="user" class="size-4"/>{{ __('blog.by', ['name' => $author]) }}</span>@endif
            </div>
            <div class="whitespace-pre-wrap wrap-anywhere border-t border-border pt-5 text-lg leading-relaxed" dir="auto">{{ $post->body }}</div>

            @if($post->tags->isNotEmpty())
                <ul class="flex flex-wrap gap-2" aria-label="{{ __('blog.tags') }}">
                    @foreach($post->tags as $tag)
                        <li class="max-w-full"><a href="{{ route('public.blog.index', ['publicLocale' => $locale, 'tag' => \App\Domain\Content\Queries\PublicBlog::keyFor($tag, $locale)]) }}"
                            class="inline-flex min-h-11 items-center rounded-full border border-border bg-surface px-4 py-2 text-sm font-semibold text-body wrap-anywhere hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ $tag->name }}</a></li>
                    @endforeach
                </ul>
            @endif
            <x-ui.link :href="$blog">{{ __('blog.back') }}</x-ui.link>
        </div>

        @if($related->isNotEmpty())
            <section aria-labelledby="blog-related" class="space-y-5 border-t border-border pt-6">
                <h2 id="blog-related" class="text-xl font-bold sm:text-2xl">{{ __('blog.related') }}</h2>
                <div class="public-card-grid grid gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related as $item)@include('blog._card', ['post' => $item])@endforeach
                </div>
            </section>
        @endif

        <x-public.info-banner :title="__('public-info.about.cta')" :description="__('public-info.about.cta_body')"
            image="images/public-info/catalog-band.webp">
            <x-public.info-course-link/>
        </x-public.info-banner>
    </article>
</x-layouts.base>
