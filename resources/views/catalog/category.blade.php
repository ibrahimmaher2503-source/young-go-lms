@php
    $locale = app()->getLocale();
    $image = $category->image ? Illuminate\Support\Facades\Storage::disk('public')->url($category->image) : null;
    $intro = (string) $category->getTranslation('description', $locale, false);
    $chip = 'inline-flex min-h-11 items-center rounded-full border border-border bg-surface px-4 text-sm font-semibold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp
<x-layouts.base :title="$seo['title']" variant="public" wide indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="space-y-6" data-category-page>
        <x-ui.breadcrumb :items="[['label' => __('catalog.title'), 'url' => route('catalog.index')], ['label' => $category->name]]"/>

        <section class="grid gap-5 rounded-card border border-border bg-surface p-4 shadow-card sm:p-6 {{ $image ? 'md:grid-cols-[minmax(0,1fr)_minmax(0,18rem)]' : '' }}" aria-labelledby="category-title" data-section="hero">
            <div class="min-w-0 space-y-3">
                <h1 id="category-title" class="break-words text-3xl font-extrabold">{{ $category->name }}</h1>
                @if(filled($intro))
                    <p class="break-words" dir="auto" data-category-intro>{!! nl2br(e($intro)) !!}</p>
                @endif
                <p class="text-sm text-muted">{{ __('catalog.results', ['count' => $courses->total()]) }}</p>
            </div>
            @if($image)<img src="{{ $image }}" alt="" class="aspect-video w-full rounded-lg object-cover">@endif
        </section>

        @if($children->isNotEmpty())
            <nav aria-label="{{ __('category_page.subcategories') }}" class="flex flex-wrap items-center gap-2" data-subcategories>
                <span class="font-semibold">{{ __('category_page.subcategories') }}</span>
                @foreach($children as $child)
                    <a href="{{ route('categories.show', ['category' => \App\Domain\Catalog\Queries\PublicCategory::keyFor($child, $locale)]) }}" class="{{ $chip }}">{{ $child->name }}</a>
                @endforeach
            </nav>
        @endif

        <section aria-labelledby="category-courses" class="min-w-0 space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="category-courses" class="text-xl font-bold">{{ __('category_page.courses') }}</h2>
                <x-ui.link :href="route('catalog.index', ['category' => $category->getKey()])">{{ __('category_page.filter_in_catalog') }}</x-ui.link>
            </div>
            @if($courses->isEmpty())
                <x-ui.card><p>{{ __('category_page.empty') }}</p></x-ui.card>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($courses as $course)
                        <x-learning.course-card :course="$course"><x-ui.link :href="route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)])" data-course-link>{{ __('catalog.view_course') }}</x-ui.link></x-learning.course-card>
                    @endforeach
                </div>
                @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
            @endif
        </section>
    </div>
</x-layouts.base>
