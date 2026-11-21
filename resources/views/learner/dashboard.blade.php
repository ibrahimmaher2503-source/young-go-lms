<x-layouts.learner :title="__('learner.dashboard')" wide>
    @php($name = \Illuminate\Support\Str::before((string) ($learner->display_name ?: $learner->name), ' '))
    <div class="space-y-6">
        <header class="space-y-1">
            <h1 class="break-words text-3xl font-extrabold sm:text-4xl">{{ __('learner.welcome_ready', ['name' => $name]) }}</h1>
            <p class="text-xl text-muted sm:hidden">{{ __('learner.welcome_sub') }}</p>
        </header>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
            <div class="min-w-0 space-y-5">
                {{-- Continue learning (hero) --}}
                <section class="rounded-card border border-border bg-surface p-4 shadow-card sm:p-5" aria-labelledby="continue-title">
                    @if($resume)
                        <div class="grid items-center gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]" data-widget="continue">
                            <x-learner.course-thumb :course="$resume['course']" :priority="true" class="md:order-2" />
                            <div class="min-w-0 space-y-3 md:order-1">
                                <p id="continue-title" class="inline-flex items-center gap-2 rounded-full bg-assistant px-4 py-2 font-semibold text-primary"><x-ui.icon name="play-circle" /> {{ __('learner.continue_badge') }}</p>
                                <div>
                                    <p class="text-muted break-words">{{ $resume['course']->title }}</p>
                                    <p class="break-words text-2xl font-extrabold">{{ $resumeTitle }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="shrink-0 text-sm text-muted">{{ __('learner.lesson_progress') }}</span>
                                    <x-ui.progress-bar class="flex-1" :label="__('learner.lesson_progress')" :value="$resumeLessonPercent" />
                                    <span class="w-12 shrink-0 text-sm font-semibold"><bdi>{{ $resumeLessonPercent }}%</bdi></span>
                                </div>
                                <a href="{{ route('learn.show', ['course' => $resume['course']->id, 'item' => $resume['continue']['item_id']]) }}"
                                    class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 text-lg font-semibold text-on-primary shadow-card sm:w-auto">{{ __('learner.continue_cta') }} <x-ui.icon name="arrow" /></a>
                            </div>
                        </div>
                    @else
                        <div class="space-y-2" data-widget="continue-empty">
                            <h2 id="continue-title" class="text-xl font-bold">{{ __('learner.continue') }}</h2>
                            <p>{{ __('learner.continue_empty') }}</p>
                            <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 font-semibold text-on-primary">{{ __('learner.browse') }}</a>
                        </div>
                    @endif
                </section>

                {{-- My enrolled courses --}}
                <section class="space-y-4 rounded-card border border-border bg-surface p-4 shadow-card sm:p-5" aria-labelledby="my-courses-title" data-widget="courses">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
                        <h2 id="my-courses-title" class="flex items-center gap-2 text-xl font-bold"><x-ui.icon name="layers" class="size-6" /> {{ __('learner.my_enrolled') }}</h2>
                        @if($total > 0)<x-ui.link :href="route('learner.courses')">{{ __('learner.see_all', ['count' => $total]) }}</x-ui.link>@endif
                    </div>
                    @if($courses->isEmpty())
                        <div class="space-y-2">
                            <p>{{ $total > 0 ? __('learner.none_in_progress') : __('learner.no_courses') }}</p>
                            <x-ui.link :href="route('catalog.index')">{{ __('learner.browse') }}</x-ui.link>
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($courses as $course)
                                <x-learner.course-row :course="$course" />
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            {{-- Side cards: schedule, tasks, certificates (slots until live sessions, assignments and certificates exist) and notifications --}}
            <aside class="min-w-0 space-y-5" aria-label="{{ __('learner.notifications') }}">
                <section class="rounded-card border border-border bg-surface p-4 shadow-card">
                    <h2 class="flex items-center justify-between gap-2 border-b border-border pb-3 text-lg font-bold"><span>{{ __('learner.schedule_title') }}</span> <x-ui.icon name="calendar" class="size-6" /></h2>
                    <div class="pt-3" data-widget="live-slot">
                        @forelse($upcoming as $row)
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-3 last:border-0" data-live-row data-live-state="{{ $row['state'] }}">
                                <div class="min-w-0">
                                    <p class="break-words font-semibold">{{ $row['session']->title }}</p>
                                    <p class="text-sm text-muted">{{ $row['course_title'] }} · {{ \App\Domain\Live\LiveSessionTime::label($row['session'], $learner) }}</p>
                                </div>
                                <a href="{{ route('learn.show', ['course' => $row['course_id'], 'item' => $row['item_id']]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-border px-3 font-semibold text-primary">{{ $row['state'] === 'joinable' ? __('learner.live_join') : __('learner.live_open_session') }}</a>
                            </div>
                        @empty
                            <p class="text-muted">{{ __('learner.upcoming_empty') }}</p>
                        @endforelse
                    </div>
                </section>
                <section class="rounded-card border border-border bg-surface p-4 shadow-card">
                    <h2 class="flex items-center justify-between gap-2 border-b border-border pb-3 text-lg font-bold"><span>{{ __('learner.tasks_title') }}</span> <x-ui.icon name="clipboard" class="size-6" /></h2>
                    <p class="pt-3 text-muted" data-widget="work-slot">{{ __('learner.pending_empty') }}</p>
                </section>
                <section class="rounded-card border border-border bg-surface p-4 shadow-card">
                    <h2 class="flex items-center justify-between gap-2 border-b border-border pb-3 text-lg font-bold"><span>{{ __('learner.certificates') }}</span> <x-ui.icon name="award" class="size-6" /></h2>
                    @forelse($certificates as $certificate)
                        <div class="border-b border-border py-3 last:border-0" data-certificate-row>
                            <p class="break-words font-semibold">{{ $certificate->titleIn(app()->getLocale()) }}</p>
                            <p class="text-sm text-muted">{{ __('certificates.issued_on', ['date' => $certificate->completed_on->isoFormat('LL')]) }}</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1">
                                <x-ui.link :href="route('certificates.download', $certificate->id)" data-certificate-download>{{ __('certificates.download') }}</x-ui.link>
                                <x-ui.link :href="$certificate->verifyUrl()">{{ __('certificates.verify_link') }}</x-ui.link>
                            </div>
                        </div>
                    @empty
                        <p class="pt-3 text-muted" data-widget="certificates-slot">{{ __('learner.certificates_empty') }}</p>
                    @endforelse
                </section>
                <section class="rounded-card border border-border bg-surface p-4 shadow-card">
                    <h2 class="flex items-center justify-between gap-2 border-b border-border pb-3 text-lg font-bold"><span>{{ __('learner.notifications') }}</span> <x-ui.icon name="bell" class="size-6" /></h2>
                    <div class="pt-2" data-widget="notifications">
                        @forelse($notifications as $notification)
                            <p class="break-words border-b border-border py-2 last:border-0">{{ $notification->data['title'] ?? $notification->data['message'] ?? __('learner.notification_generic') }}</p>
                        @empty
                            <p class="pt-1 text-muted">{{ __('learner.notifications_empty') }}</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.learner>
