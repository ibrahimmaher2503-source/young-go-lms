<x-layouts.learner :title="__('learner.my_courses')" wide>
    <div class="space-y-6">
        <h1 class="text-3xl font-bold">{{ __('learner.my_courses') }}</h1>

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
                    <x-learner.course-row :course="$course" />
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.learner>
