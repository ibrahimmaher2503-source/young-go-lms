<x-layouts.learner :title="__('parent.title')" wide>
    @php($childName = fn ($c) => $c->display_name ?: $c->name)
    <div class="space-y-5">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-1">
                <h1 class="break-words text-3xl font-extrabold sm:text-4xl">{{ __('parent.title') }}</h1>
                <p class="text-lg text-muted">{{ __('parent.subtitle') }}</p>
            </div>
            <nav aria-label="{{ __('parent.switch_child') }}" class="flex flex-wrap items-center gap-2" data-child-switcher>
                @foreach($children as $child)
                    @php($current = $selected && $selected->is($child))
                    <a href="{{ route('parent.dashboard', ['child' => $child->id]) }}" @if($current) aria-current="true" @endif
                        class="inline-flex min-h-12 items-center gap-2 rounded-xl border-2 px-4 font-semibold {{ $current ? 'border-primary bg-nav-active-bg text-nav-active-text' : 'border-border bg-surface' }}">
                        <x-ui.avatar :name="$childName($child)" class="size-8" />
                        <span class="break-words">{{ $childName($child) }}</span>
                        @if($current)<x-ui.icon name="check" class="size-4" />@endif
                    </a>
                @endforeach
                <a href="{{ url('/family#add-child') }}" class="inline-flex min-h-12 items-center gap-2 rounded-xl border-2 border-border bg-surface px-4 font-semibold text-primary"><x-ui.icon name="plus" class="size-4" /> {{ __('parent.add_child') }}</a>
            </nav>
        </header>

        @if($selected === null)
            <x-ui.card :title="__('parent.no_children_title')" data-empty>
                <p>{{ __('parent.no_children') }}</p>
                <x-ui.link :href="url('/family#add-child')">{{ __('parent.add_child') }}</x-ui.link>
            </x-ui.card>
        @else
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-card bg-assistant px-4 py-3 text-lg font-semibold text-primary">
                <p class="flex items-center gap-2" role="status" data-viewing><x-ui.icon name="user" /> {{ __('parent.viewing', ['name' => $childName($selected)]) }}</p>
                <x-family.child-switcher :child="$selected" :success="url('/my')" />
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
                <div class="min-w-0 space-y-5">
                    <section class="rounded-card border border-border bg-surface p-4 shadow-card sm:p-5" aria-labelledby="summary-title" data-widget="summary">
                        <h2 id="summary-title" class="flex items-center justify-between gap-2 border-b border-border pb-3 text-xl font-bold"><span>{{ __('parent.summary_of', ['name' => $childName($selected)]) }}</span> <x-ui.icon name="clipboard" class="size-6" /></h2>
                        <dl class="grid gap-4 pt-3 sm:grid-cols-4">
                            <div><dt class="text-sm text-muted">{{ __('parent.summary_courses') }}</dt><dd class="text-2xl font-extrabold">{{ $summary['courses'] }}</dd></div>
                            <div><dt class="text-sm text-muted">{{ __('parent.summary_completed') }}</dt><dd class="text-2xl font-extrabold">{{ $summary['completed'] }}</dd></div>
                            <div><dt class="text-sm text-muted">{{ __('parent.summary_lessons') }}</dt><dd class="text-2xl font-extrabold"><bdi>{{ $summary['lessons_done'] }} / {{ $summary['lessons_total'] }}</bdi></dd></div>
                            <div><dt class="text-sm text-muted">{{ __('parent.summary_average') }}</dt><dd class="text-2xl font-extrabold"><bdi>{{ $summary['average'] }}%</bdi></dd></div>
                        </dl>
                    </section>

                    <section class="space-y-3 rounded-card border border-border bg-surface p-4 shadow-card sm:p-5" aria-labelledby="child-courses" data-widget="child-courses">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
                            <h2 id="child-courses" class="flex items-center gap-2 text-xl font-bold"><x-ui.icon name="layers" class="size-6" /> {{ __('parent.courses_of', ['name' => $childName($selected)]) }}</h2>
                        </div>
                        @forelse($courses as $row)
                            @php($instructors = $row['course']->instructors->where('status', 'active')->pluck('name')->implode(', '))
                            <article class="grid items-center gap-3 border-b border-border pb-3 last:border-0 last:pb-0 sm:grid-cols-[6rem_minmax(0,1fr)_minmax(0,16rem)]" data-course-status="{{ $row['status'] }}">
                                <x-learner.course-thumb :course="$row['course']" class="hidden sm:grid" />
                                <div class="min-w-0">
                                    <h3 class="break-words text-lg font-bold">{{ $row['course']->title }}</h3>
                                    @if($instructors !== '')<p class="text-sm text-muted">{{ __('parent.instructor', ['name' => $instructors]) }}</p>@endif
                                </div>
                                @if($row['status'] === 'expired')
                                    <x-ui.badge tone="warning">{{ __('learner.filters.expired') }}</x-ui.badge>
                                @else
                                    <div class="space-y-1">
                                        <p class="text-sm">{{ trans_choice('parent.completed_of_choice', $row['total'], ['done' => $row['done'], 'total' => $row['total']]) }}</p>
                                        <div class="flex items-center gap-3">
                                            <x-ui.progress-bar class="flex-1" :label="__('learn.course_progress_label').': '.$row['course']->title" :value="$row['percent']" />
                                            <span class="w-12 shrink-0 text-sm font-semibold"><bdi>{{ $row['percent'] }}%</bdi></span>
                                        </div>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="text-muted" data-widget="child-courses-empty">{{ __('parent.no_courses', ['name' => $childName($selected)]) }}</p>
                        @endforelse
                        @if($courses->hasPages())<x-ui.pagination :paginator="$courses" />@endif
                    </section>

                    <section class="rounded-card border border-border bg-surface p-4 shadow-card sm:p-5" aria-labelledby="results-title">
                        <h2 id="results-title" class="flex items-center justify-between gap-2 border-b border-border pb-3 text-xl font-bold"><span>{{ __('parent.results_title') }}</span> <x-ui.icon name="award" class="size-6" /></h2>
                        <p class="pt-3 text-muted" data-widget="results-slot">{{ __('parent.results_empty') }}</p>
                    </section>
                </div>

                <aside class="min-w-0 space-y-5" aria-label="{{ __('parent.side') }}">
                    <section class="rounded-card border border-border bg-surface p-4 shadow-card">
                        <h2 class="flex items-center justify-between gap-2 border-b border-border pb-3 text-lg font-bold"><span>{{ __('parent.upcoming_sessions') }}</span> <x-ui.icon name="calendar" class="size-6" /></h2>
                        <p class="pt-3 text-muted" data-widget="live-slot">{{ __('parent.sessions_empty') }}</p>
                    </section>
                    <section class="rounded-card border border-border bg-surface p-4 shadow-card">
                        <h2 class="flex items-center justify-between gap-2 border-b border-border pb-3 text-lg font-bold"><span>{{ __('parent.upcoming_work') }}</span> <x-ui.icon name="clipboard" class="size-6" /></h2>
                        <p class="pt-3 text-muted" data-widget="work-slot">{{ __('parent.work_empty') }}</p>
                    </section>
                    <section class="rounded-card border border-border bg-surface p-4 shadow-card">
                        <h2 class="flex items-center justify-between gap-2 border-b border-border pb-3 text-lg font-bold"><span>{{ __('parent.help_requests') }}</span> <x-ui.icon name="help" class="size-6" /></h2>
                        <p class="pt-3 text-muted" data-widget="help-slot">{{ __('parent.help_empty') }}</p>
                    </section>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.learner>
