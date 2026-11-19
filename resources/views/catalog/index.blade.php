@php
    // Count of active filters that live inside the filter panel (search and sort are visible controls of their own).
    $panelFilters = count(array_diff_key($filters, ['q' => true]));
    $categoryChoices = $options['category'] ?? [];
    $chipClass = 'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp
<x-layouts.base :title="__('catalog.title')" variant="public" indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>
    <div class="space-y-6">
        <header id="catalog-filters" class="space-y-4 rounded-card border border-border bg-assistant p-4 sm:p-6" data-catalog-hero>
            <div class="space-y-1">
                <h1 class="text-3xl font-bold">{{ __('catalog.hero_title') }}</h1>
                <p class="text-muted">{{ __('catalog.hero_subtitle') }}</p>
            </div>
            <form method="get" action="{{ route('catalog.index') }}" class="flex flex-wrap items-end gap-3" role="search">
                @foreach($filters as $name => $value)
                    @if($name !== 'q')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                @endforeach
                <div class="min-w-0 flex-1">
                    <label for="catalog-search" class="mb-1 block font-semibold">{{ __('catalog.search') }}</label>
                    <x-ui.input id="catalog-search" name="q" type="search" :value="$filters['q'] ?? ''" maxlength="160" :placeholder="__('catalog.search_placeholder')"/>
                </div>
                <x-ui.button type="submit">{{ __('catalog.find') }}</x-ui.button>
            </form>
            @if($categoryChoices !== [])
                <nav aria-label="{{ __('catalog.category_chips') }}" data-category-chips>
                    <ul class="flex flex-wrap gap-2">
                        <li><a href="{{ route('catalog.index', array_diff_key($filters, ['category' => true])) }}" @if(! isset($filters['category'])) aria-current="true" @endif class="{{ $chipClass }} {{ ! isset($filters['category']) ? 'border-primary bg-primary text-on-primary' : 'border-border bg-surface text-body' }}"><x-ui.icon name="layers" class="size-4"/>{{ __('catalog.all_categories') }}</a></li>
                        @foreach($categoryChoices as $id => $name)
                            @php($on = (string) ($filters['category'] ?? '') === (string) $id)
                            <li><a href="{{ route('catalog.index', array_replace($filters, ['category' => $id])) }}" @if($on) aria-current="true" @endif class="{{ $chipClass }} {{ $on ? 'border-primary bg-primary text-on-primary' : 'border-border bg-surface text-body' }}"><bdi>{{ $name }}</bdi></a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </header>

        <div class="lg:hidden" data-filters-collapsible>
            <details class="rounded-xl border border-border bg-surface px-4" @if($panelFilters > 0) open @endif>
                <summary class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md py-3 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" aria-label="{{ __('catalog.filters_toggle') }}">
                    <x-ui.icon name="filter"/>
                    <span>{{ __('catalog.filters') }}</span>
                    @if($panelFilters > 0)<x-ui.badge tone="info" data-filter-count>{{ trans_choice('catalog.filters_active', $panelFilters, ['count' => $panelFilters]) }}</x-ui.badge>@endif
                    <x-ui.icon name="chevron-down" class="ms-auto"/>
                </summary>
                <div class="pb-4">
                    @include('catalog.filters', ['prefix' => 'mobile', 'market' => $market, 'sorts' => $sorts])
                </div>
            </details>
        </div>

        <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <aside class="hidden lg:block" aria-label="{{ __('catalog.filters') }}">
                <x-ui.card>
                    <h2 class="mb-4 flex items-center gap-2 text-lg font-bold"><x-ui.icon name="filter"/>{{ __('catalog.filters') }}@if($panelFilters > 0)<x-ui.badge tone="info" data-filter-count>{{ $panelFilters }}</x-ui.badge>@endif</h2>
                    @include('catalog.filters', ['prefix' => 'desktop', 'market' => $market, 'sorts' => $sorts])
                </x-ui.card>
            </aside>
            <section aria-labelledby="catalog-results" class="min-w-0 space-y-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="catalog-results" class="text-xl font-bold">{{ __('catalog.results', ['count' => $courses->total()]) }}</h2>
                    @if($courses->isNotEmpty())<span class="text-sm" data-sort-label>{{ $sorts[$filters['sort'] ?? 'newest'] }}</span>@endif
                </div>
                @if($active !== [])
                    <ul class="flex flex-wrap items-center gap-2" aria-label="{{ __('catalog.active_filters') }}" data-active-filters>
                        @foreach($active as $chip)
                            <li><a href="{{ $chip['url'] }}" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-surface px-4 text-sm font-semibold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" aria-label="{{ __('catalog.remove_filter', ['name' => $chip['label']]) }}"><bdi>{{ $chip['label'] }}</bdi><span aria-hidden="true">&times;</span></a></li>
                        @endforeach
                        <li><x-ui.link :href="route('catalog.index')">{{ __('catalog.clear_all') }}</x-ui.link></li>
                    </ul>
                @endif
                @if($courses->isEmpty())
                    <x-ui.empty-state :title="__('catalog.empty_title')" :description="__('catalog.empty_hint')" data-catalog-empty><p class="sr-only">{{ __('catalog.empty') }}</p><x-ui.link :href="route('catalog.index')">{{ __('catalog.clear_all') }}</x-ui.link></x-ui.empty-state>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($courses as $course)
                            <x-learning.course-card :course="$course" :wishlist="true" :placeholder="true" :priority="$loop->first" :price="$prices[$course->id] ?? null" :market="$market" :availability="$availability[$course->id] ?? null" :lessons="$lessonCounts[$course->id] ?? null"><x-ui.link :href="route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)])" data-course-link>{{ __('catalog.view_course') }}</x-ui.link></x-learning.course-card>
                        @endforeach
                    </div>
                    @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
                @endif
            </section>
        </div>
    </div>
</x-layouts.base>
