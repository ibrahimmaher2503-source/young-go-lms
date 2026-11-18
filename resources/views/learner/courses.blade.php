<x-layouts.learner :title="__('learner.my_courses')" wide>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-3xl font-bold">{{ __('learner.my_courses') }}</h1>
            <x-ui.link :href="route('wishlist.index')" data-wishlist-link>{{ __('engagement.wishlist_title') }}</x-ui.link>
        </div>

        <nav aria-label="{{ __('learner.filter') }}" class="flex flex-wrap gap-2">
            @foreach($filters as $name)
                <a href="{{ route('learner.courses', $name === 'all' ? [] : ['status' => $name]) }}"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border px-4 font-semibold {{ $filter === $name ? 'bg-primary text-on-primary' : 'bg-surface' }}"
                    @if($filter === $name) aria-current="page" @endif data-filter="{{ $name }}">
                    {{ __('learner.filters.'.$name) }} <span>({{ $counts[$name] }})</span>
                </a>
            @endforeach
        </nav>

        @if($courses->isEmpty())
            <x-ui.card data-empty>
                <p>{{ $counts['all'] === 0 ? __('learner.no_courses') : __('learner.empty_filter') }}</p>
                <x-ui.link :href="route('catalog.index')">{{ __('learner.browse') }}</x-ui.link>
            </x-ui.card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $course)
                    <x-learner.course-row :course="$course" :certificate="$certificates->get($course['course']->id)" />
                @endforeach
            </div>
        @endif
        @if($courses->hasPages())<x-ui.pagination :paginator="$courses" />@endif
    </div>
</x-layouts.learner>
