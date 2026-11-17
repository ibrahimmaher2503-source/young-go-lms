@php($title = (string) ($entity?->title ?? $course->title))
<x-layouts.base :title="$title" variant="student">
    <div class="space-y-4">
        <nav aria-label="{{ __('pages.main_nav') }}" class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold">{{ $course->title }}</span>
            <x-ui.badge>{{ __('learn.types.'.($lesson?->type ?? $item->itemable_type)) }}</x-ui.badge>
            @if($item->itemable instanceof \App\Models\Lesson && $item->itemable->is_preview && ! $learner->can('viewAny', $course))
                {{-- preview badge is shown from the curriculum list state --}}
            @endif
        </nav>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-4">
                <h1 class="text-2xl font-bold break-words">{{ $title }}</h1>
                @if($courseProgress)
                    <div class="space-y-1" data-course-progress>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span>{{ __('learn.course_progress', ['done' => $courseProgress->completed_required, 'total' => $courseProgress->total_required]) }}</span>
                            <span data-course-percent>{{ $courseProgress->percent }}%</span>
                        </div>
                        <x-ui.progress-bar :label="__('learn.course_progress_label')" :value="$courseProgress->percent" />
                    </div>
                @endif

                @if(! $decision->allowed)
                    <x-ui.card :title="__('learn.locked_title')">
                        <p role="status" data-locked-reason>{{ $decision->message() }}</p>
                    </x-ui.card>
                @elseif($lesson === null)
                    <x-ui.card><p>{{ __('learn.assessment_soon') }}</p></x-ui.card>
                @else
                    <section aria-label="{{ __('learn.now_playing') }}" data-player data-ai-available="{{ $aiAvailable ? 'true' : 'false' }}" class="space-y-3"
                        @if($entitled) data-progress-url="{{ route('learn.progress', ['course' => $course->id, 'item' => $item->id]) }}" data-complete-url="{{ route('learn.complete', ['course' => $course->id, 'item' => $item->id]) }}" data-resume="{{ $lessonProgress?->position_seconds ?? 0 }}" @endif>
                        <div class="relative overflow-hidden rounded-xl border border-border bg-surface" data-player-stage>
                            @if($descriptor === null)
                                <p class="p-6">{{ __('learn.no_media') }}</p>
                            @else
                                <x-learning.lesson-media :descriptor="$descriptor" :title="$title" />
                                @if(in_array($descriptor->type, ['video', 'audio'], true))
                                    <span class="pointer-events-none absolute end-3 top-3 select-none text-sm opacity-40" data-watermark aria-hidden="true">{{ $learner->display_name ?: $learner->name }}</span>
                                @endif
                            @endif
                        </div>

                        @if($descriptor !== null && in_array($descriptor->type, ['video', 'audio'], true))
                            <div role="toolbar" aria-label="{{ __('learn.toolbar') }}" class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex items-center gap-2">
                                    <span class="font-semibold">{{ __('learn.speed') }}</span>
                                    <select data-player-speed class="min-h-11 rounded-lg border border-border bg-surface px-2">
                                        @foreach([0.75, 1, 1.25, 1.5, 2] as $speed)
                                            <option value="{{ $speed }}" @selected($speed === 1)>{{ $speed }}×</option>
                                        @endforeach
                                    </select>
                                </label>
                                @if($descriptor->tracks !== [])
                                    <x-ui.button type="button" variant="secondary" data-player-captions aria-pressed="false">{{ __('learn.captions') }}</x-ui.button>
                                @endif
                                @if($descriptor->type === 'video')
                                    <x-ui.button type="button" variant="secondary" data-player-fullscreen>{{ __('learn.fullscreen') }}</x-ui.button>
                                @endif
                            </div>
                        @endif

                        @if($curriculumPdfUrl || $resources)
                            <x-ui.card class="p-3 sm:p-4">
                                <x-ui.tabs id="lesson-materials" class="lesson-materials" :label="__('learn.resources')" :items="array_filter(['pdf' => $curriculumPdfUrl ? __('learn.lesson_pdf') : null, 'attachments' => $resources ? __('learn.resources') : null])">
                                    <x-slot:pdf>
                                        @if($curriculumPdfUrl)
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-danger text-sm font-semibold text-on-danger" aria-hidden="true">PDF</span>
                                                    <div>
                                                        <p class="font-semibold break-words">{{ __('learn.lesson_pdf_title', ['title' => $title]) }}</p>
                                                        <p class="text-sm text-muted"><bdi>{{ \Illuminate\Support\Number::fileSize($curriculumPdfSize, 1) }}</bdi></p>
                                                    </div>
                                                </div>
                                                <x-ui.link :href="$curriculumPdfUrl" class="min-w-11 justify-center border border-border" :aria-label="__('learn.download_lesson_pdf')">
                                                    <x-ui.icon name="download" />
                                                </x-ui.link>
                                            </div>
                                        @endif
                                    </x-slot:pdf>
                                    <x-slot:attachments>
                                        <ul class="space-y-2">
                                            @foreach($resources as $resource)
                                                <li>
                                                    <x-ui.link :href="$resource['url']" class="w-full justify-between gap-2 border border-border px-3 no-underline hover:bg-page">
                                                        <span class="break-words">{{ __('learn.open_file', ['name' => $resource['title']]) }}</span>
                                                        <x-ui.icon name="chevron" />
                                                    </x-ui.link>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </x-slot:attachments>
                                </x-ui.tabs>
                            </x-ui.card>
                        @endif
                        @if($descriptor !== null && in_array($descriptor->type, ['video', 'audio'], true))
                            <p class="text-sm">{{ __('learn.shortcuts') }}</p>
                        @endif

                        @if($lessonProgress?->status === 'completed')
                            <x-ui.badge tone="success" data-lesson-completed>{{ __('learn.completed') }}</x-ui.badge>
                        @elseif($canMarkComplete)
                            <x-ui.button type="button" variant="secondary" data-player-complete>{{ __('learn.mark_complete') }}</x-ui.button>
                        @endif

                        @if($aiAvailable)
                            <div data-ai-slot aria-label="{{ __('learn.assistant') }}"></div>
                        @endif
                    </section>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-2">
                    @if($neighbours['previous'])
                        <a href="{{ route('learn.show', ['course' => $course->id, 'item' => $neighbours['previous']]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 font-semibold" rel="prev">{{ __('learn.previous') }}</a>
                    @else <span></span> @endif
                    @if($neighbours['next'])
                        <a href="{{ route('learn.show', ['course' => $course->id, 'item' => $neighbours['next']]) }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 font-semibold text-on-primary" rel="next">{{ __('learn.next') }}</a>
                    @endif
                </div>
            </div>

            <div class="lg:hidden">
                <x-ui.drawer id="curriculum-drawer" :title="__('learn.curriculum')" :trigger="__('learn.open_curriculum')">
                    <x-learning.curriculum-list :course="$course" :sections="$sections" :current="$item->id" :completed="$completed" prefix="drawer" />
                </x-ui.drawer>
            </div>
            <aside class="hidden lg:block" aria-label="{{ __('learn.curriculum') }}">
                <x-learning.curriculum-list :course="$course" :sections="$sections" :current="$item->id" :completed="$completed" prefix="side" />
            </aside>
        </div>
    </div>
</x-layouts.base>
