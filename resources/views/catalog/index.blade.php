<x-layouts.base :title="__('catalog.title')" variant="public" indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>
    <div class="space-y-6">
        <h1 class="text-3xl font-bold">{{ __('catalog.title') }}</h1>
        <form id="catalog-filters" method="get" action="{{ route('catalog.index') }}" class="flex flex-wrap items-end gap-3" role="search">
            @foreach($filters as $name => $value)
                @if($name !== 'q')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
            @endforeach
            <div class="min-w-0 flex-1">
                <label for="catalog-search" class="mb-1 block font-semibold">{{ __('catalog.search') }}</label>
                <x-ui.input id="catalog-search" name="q" type="search" :value="$filters['q'] ?? ''" maxlength="160"/>
            </div>
            <x-ui.button type="submit">{{ __('catalog.find') }}</x-ui.button>
        </form>

        <div class="lg:hidden">
            <x-ui.modal id="catalog-filters" :title="__('catalog.filters')" :trigger="__('catalog.filters')">
                @include('catalog.filters', ['prefix' => 'mobile', 'market' => $market, 'sorts' => $sorts])
            </x-ui.modal>
        </div>

        <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <aside class="hidden lg:block" aria-label="{{ __('catalog.filters') }}">
                <x-ui.card :title="__('catalog.filters')">
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
                            <x-learning.course-card :course="$course" :wishlist="true" :price="$prices[$course->id] ?? null" :market="$market" :availability="$availability[$course->id] ?? null"><x-ui.link :href="route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)])" data-course-link>{{ __('catalog.view_course') }}</x-ui.link></x-learning.course-card>
                        @endforeach
                    </div>
                    @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
                @endif
            </section>
        </div>
    </div>
</x-layouts.base>
