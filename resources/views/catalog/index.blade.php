@php
    // Count of active filters that live inside the filter panel (search and sort are visible controls of their own).
    $panelFilters = count(array_diff_key($filters, ['q' => true, 'sort' => true]));
    $catalogUrl = static fn (array $query = []): string => route($catalogRoute, $catalogParameters + $query);
    $categoryChoices = $options['category'] ?? [];
    $chipClass = 'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp
<x-layouts.base :title="__('catalog.title')" :locale-alternates="$seo['alternates']" variant="public" indexable full>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>
    <div class="public-page-stack">
        <header id="catalog-filters" class="public-band public-band-header scroll-mt-4 space-y-3" data-catalog-hero>
            <div class="space-y-1">
                <h1 class="text-3xl leading-tight font-extrabold sm:text-4xl">{{ __('catalog.hero_title') }}</h1>
                <p class="max-w-prose text-muted sm:text-lg">{{ __('catalog.hero_subtitle') }}</p>
            </div>
            <form method="get" action="{{ $catalogUrl() }}" class="flex items-center gap-2 rounded-xl border border-border bg-surface p-1 shadow-card focus-within:border-primary" role="search">
                @foreach(array_diff_key($filters, ['q' => true]) as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <label for="catalog-search" class="sr-only">{{ __('catalog.search') }}</label>
                <div class="relative min-w-0 flex-1">
                    <x-ui.icon name="search" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-muted"/>
                    <x-ui.input id="catalog-search" name="q" type="search" :value="$filters['q'] ?? ''" maxlength="160" :placeholder="__('catalog.search_placeholder')" class="!border-transparent !ps-10"/>
                </div>
                <x-ui.button type="submit" size="sm">{{ __('catalog.find') }}</x-ui.button>
            </form>
            @if($categoryChoices !== [])
                <nav aria-label="{{ __('catalog.category_chips') }}" data-category-chips class="overflow-x-auto px-1 pb-2 pt-1">
                    <ul class="flex w-max min-w-full gap-2 sm:w-full sm:flex-wrap sm:justify-center">
                        @foreach(['' => __('catalog.all_categories')] + $categoryChoices as $id => $name)
                            @php($on = (string) ($filters['category'] ?? '') === (string) $id)
                            <li>
                                <a href="{{ $catalogUrl($id === '' ? array_diff_key($filters, ['category' => true]) : array_replace($filters, ['category' => $id])) }}"
                                    @if($on) aria-current="true" @endif
                                    class="{{ $chipClass }} {{ $on ? 'border-nav-active-bg bg-nav-active-bg text-nav-active-text' : 'border-border bg-surface text-body hover:bg-hover' }}">
                                    @if($id === '')<x-ui.icon name="layers" class="size-4"/>@endif
                                    <bdi>{{ $name }}</bdi>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </header>

        <div class="grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)] xl:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="hidden lg:block" aria-label="{{ __('catalog.filters') }}">
                <x-ui.card class="shadow-card">
                    <h2 class="mb-3 flex items-center gap-2 text-lg font-bold"><x-ui.icon name="filter" class="text-primary"/>{{ __('catalog.filters') }}@if($panelFilters > 0)<x-ui.badge tone="info" data-filter-count>{{ $panelFilters }}</x-ui.badge>@endif</h2>
                    @include('catalog.filters', ['prefix' => 'desktop'])
                </x-ui.card>
            </aside>
            <section aria-labelledby="catalog-results" class="min-w-0 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="catalog-results" class="text-base font-semibold text-muted">{{ trans_choice('catalog.results', $courses->total(), ['count' => $courses->total()]) }}</h2>
                    <div class="lg:hidden" data-filters-collapsible>
                        <x-ui.drawer id="catalog-filter-drawer" :title="__('catalog.filters')" placement="side" trigger-size="sm">
                            <x-slot:trigger><x-ui.icon name="filter" class="size-4"/>{{ __('catalog.filters') }}@if($panelFilters > 0)<x-ui.badge tone="info" data-filter-count>{{ $panelFilters }}</x-ui.badge>@endif</x-slot:trigger>
                            @include('catalog.filters', ['prefix' => 'mobile'])
                        </x-ui.drawer>
                    </div>
                    @if($courses->isNotEmpty() || isset($filters['sort']))
                        <form method="get" action="{{ $catalogUrl() }}" class="flex min-w-0 w-full items-center gap-2 sm:w-auto">
                            @foreach(array_diff_key($filters, ['sort' => true]) as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <label for="catalog-sort" class="shrink-0 text-sm font-semibold">{{ __('catalog.sort') }}</label>
                            <x-ui.select id="catalog-sort" name="sort" class="min-w-0 text-sm">
                                @foreach($sorts as $key => $label)
                                    <option value="{{ $key }}" @selected(($filters['sort'] ?? 'newest') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.button type="submit" variant="secondary" size="sm">{{ __('catalog.apply') }}</x-ui.button>
                        </form>
                    @endif
                </div>
                @if($active !== [])
                    <ul class="flex flex-wrap items-center gap-2" aria-label="{{ __('catalog.active_filters') }}" data-active-filters>
                        @foreach($active as $chip)
                            <li><a href="{{ $chip['url'] }}" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-surface px-4 text-sm font-semibold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" aria-label="{{ __('catalog.remove_filter', ['name' => $chip['label']]) }}"><bdi>{{ $chip['label'] }}</bdi><span aria-hidden="true">&times;</span></a></li>
                        @endforeach
                        <li><x-ui.link :href="$catalogUrl()">{{ __('catalog.clear_all') }}</x-ui.link></li>
                    </ul>
                @endif
                @if($courses->isEmpty())
                    <x-ui.empty-state :title="__('catalog.empty_title')" :description="__('catalog.empty_hint')" icon="search" class="bg-surface sm:py-10" data-catalog-empty><p class="sr-only">{{ __('catalog.empty') }}</p><x-ui.link :href="$catalogUrl()">{{ __('catalog.clear_all') }}</x-ui.link></x-ui.empty-state>
                @else
                    <div class="public-card-grid grid gap-x-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($courses as $course)
                            <x-learning.course-card :course="$course" :wishlist="true" :placeholder="true" :priority="$loop->first" :price="$prices[$course->id] ?? null" :market="$market" :availability="$availability[$course->id] ?? null" :lessons="$lessonCounts[$course->id] ?? null" catalog>
                                <a href="{{ \App\Domain\Seo\PublicUrl::course($course, app()->getLocale()) }}" data-course-link class="inline-flex min-h-11 items-center gap-1.5 rounded-md text-sm font-bold text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                    {{ __('catalog.view_course') }}<x-ui.icon name="chevron" class="size-4"/>
                                </a>
                            </x-learning.course-card>
                        @endforeach
                    </div>
                    @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
                @endif
            </section>
        </div>
    </div>
</x-layouts.base>
