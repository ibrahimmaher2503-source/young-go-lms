{{-- Props: post (BlogPost, loaded with category), href (optional). --}}
@php
    $locale = app()->getLocale();
    $href ??= \App\Domain\Seo\PublicUrl::blogPost($post, $locale);
    $category = $post->category?->status === \App\Models\BlogCategory::ACTIVE ? $post->category : null;
@endphp
<article class="flex h-full min-w-0 flex-col overflow-hidden rounded-card border border-border bg-surface" data-blog-card>
    @if(filled($post->featured_image))
        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($post->featured_image) }}" alt="{{ $post->getTranslation('featured_image_alt', $locale, false) }}"
            width="640" height="360" loading="lazy" decoding="async" class="aspect-video w-full border-b border-border object-cover">
    @endif
    <div class="flex flex-1 flex-col items-start gap-3 p-5">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-muted">
            @if($category)<x-ui.badge>{{ $category->name }}</x-ui.badge>@endif
            <time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->locale($locale)->isoFormat('LL') }}</time>
        </div>
        <h3 class="wrap-anywhere text-lg font-bold leading-snug">{{ $post->title }}</h3>
        @if(filled($post->excerpt))<p class="wrap-anywhere text-sm leading-relaxed text-muted" dir="auto">{{ $post->excerpt }}</p>@endif
        <x-ui.link :href="$href" class="mt-auto gap-2 text-sm" :aria-label="__('blog.read_more').' · '.$post->title">
            {{ __('blog.read_more') }} <x-ui.icon name="chevron" class="size-4"/>
        </x-ui.link>
    </div>
</article>
