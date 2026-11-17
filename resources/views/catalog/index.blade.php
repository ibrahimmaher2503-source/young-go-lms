<x-layouts.base :title="__('catalog.title')" variant="public">
    <div class="space-y-6">
        <h1 class="text-3xl font-bold">{{ __('catalog.title') }}</h1>
        <form method="get" action="{{ route('catalog.index') }}" class="flex flex-wrap items-end gap-3" role="search">
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
                @include('catalog.filters', ['prefix' => 'mobile'])
            </x-ui.modal>
        </div>

        <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <aside class="hidden lg:block" aria-label="{{ __('catalog.filters') }}">
                <x-ui.card :title="__('catalog.filters')">
                    @include('catalog.filters', ['prefix' => 'desktop'])
                </x-ui.card>
            </aside>
            <section aria-labelledby="catalog-results" class="min-w-0 space-y-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="catalog-results" class="text-xl font-bold">{{ __('catalog.results', ['count' => $courses->total()]) }}</h2>
                    @if($courses->isNotEmpty())<span class="text-sm">{{ __('catalog.newest') }}</span>@endif
                </div>
                @if($courses->isEmpty())
                    <x-ui.card><p>{{ __('catalog.empty') }}</p></x-ui.card>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($courses as $course)
                            <x-learning.course-card :course="$course"/>
                        @endforeach
                    </div>
                    @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
                @endif
            </section>
        </div>
    </div>
</x-layouts.base>
