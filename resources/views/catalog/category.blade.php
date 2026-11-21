@php
    $locale = app()->getLocale();
    $image = $category->image ? Illuminate\Support\Facades\Storage::disk('public')->url($category->image) : null;
    $intro = (string) $category->getTranslation('description', $locale, false);
    $catalog = \App\Domain\Seo\PublicUrl::catalog($locale);
    $filteredCatalog = route('public.catalog.index', ['publicLocale' => $locale, 'category' => $category->getKey()]);
@endphp
<x-layouts.base :title="$seo['title']" :locale-alternates="$seo['alternates']" variant="public" wide indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="public-page-stack" data-category-page>
        <x-ui.breadcrumb :items="[['label' => __('catalog.title'), 'url' => $catalog], ['label' => $category->name]]"/>

        <section @class([
            'grid overflow-hidden rounded-card border border-border bg-assistant',
            'md:grid-cols-2' => $image,
        ]) aria-labelledby="category-title" data-section="hero">
            <div class="min-w-0 self-center space-y-4 p-6 sm:p-8">
                <h1 id="category-title" class="wrap-anywhere text-3xl font-extrabold leading-tight sm:text-4xl">{{ $category->name }}</h1>
                @if(filled($intro))
                    <p class="max-w-prose whitespace-pre-line wrap-anywhere leading-relaxed text-muted" dir="auto" data-category-intro>{{ $intro }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <a href="{{ $filteredCatalog }}" class="inline-flex min-h-11 max-w-full items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-on-primary hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        <x-ui.icon name="filter" class="size-4"/>
                        {{ __('category_page.filter_in_catalog') }}
                    </a>
                    <span class="text-sm font-semibold text-muted">{{ trans_choice('catalog.results', $courses->total(), ['count' => $courses->total()]) }}</span>
                </div>
            </div>
            @if($image)
                <img src="{{ $image }}" alt="" width="640" height="360" loading="eager" fetchpriority="high" decoding="async"
                    class="aspect-video h-full w-full border-t border-border object-cover md:border-s md:border-t-0">
            @endif
        </section>

        @if($children->isNotEmpty())
            <nav aria-labelledby="category-children" class="space-y-3" data-subcategories>
                <h2 id="category-children" class="text-lg font-bold">{{ __('category_page.subcategories') }}</h2>
                <ul class="flex flex-wrap gap-2">
                    @foreach($children as $child)
                        <li class="max-w-full">
                            <a href="{{ \App\Domain\Seo\PublicUrl::category($child, $locale) }}"
                                class="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-surface px-4 py-2 text-sm font-semibold text-body wrap-anywhere hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                {{ $child->name }} <x-ui.icon name="chevron" class="size-4"/>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <section aria-labelledby="category-courses" class="min-w-0 space-y-5">
            <h2 id="category-courses" class="text-xl font-bold sm:text-2xl">{{ __('category_page.courses') }}</h2>
            @if($courses->isEmpty())
                <x-ui.empty-state :title="__('category_page.empty')" icon="book"/>
            @else
                <div class="public-card-grid grid gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($courses as $course)
                        <x-learning.course-card :course="$course" placeholder catalog>
                            <x-ui.link :href="\App\Domain\Seo\PublicUrl::course($course, $locale)" class="text-sm" data-course-link>{{ __('catalog.view_course') }}</x-ui.link>
                        </x-learning.course-card>
                    @endforeach
                </div>
                @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
            @endif
        </section>
    </div>
</x-layouts.base>
