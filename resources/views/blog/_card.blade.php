{{-- Props: post (BlogPost, loaded with category). One article card for the blog index and related posts. --}}
@php
    $locale = app()->getLocale();
    $key = \App\Domain\Content\Queries\PublicBlog::keyFor($post, $locale);
    $category = $post->category?->status === \App\Models\BlogCategory::ACTIVE ? $post->category : null;
@endphp
<article class="min-w-0 space-y-3 rounded-xl border border-border bg-surface p-4" data-blog-card>
    @if(filled($post->featured_image))
        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($post->featured_image) }}" alt="{{ $post->getTranslation('featured_image_alt', $locale, false) }}"
            width="640" height="360" loading="lazy" decoding="async" class="aspect-video w-full rounded-lg object-cover">
    @endif
    <div class="flex flex-wrap items-center gap-2 text-sm">
        @if($category)<x-ui.badge>{{ $category->name }}</x-ui.badge>@endif
        <time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->locale($locale)->isoFormat('LL') }}</time>
    </div>
    <h3 class="break-words text-lg font-bold"><a href="{{ route('blog.show', ['post' => $key]) }}" class="focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ $post->title }}</a></h3>
    @if(filled($post->excerpt))<p class="break-words">{{ $post->excerpt }}</p>@endif
    <x-ui.link :href="route('blog.show', ['post' => $key])">{{ __('blog.read_more') }}</x-ui.link>
</article>
