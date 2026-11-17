@php
    $preview = $preview ?? false;
    $showRoute = $preview ? 'learn.preview' : 'learn.show';
    $title = (string) ($entity?->title ?? $course->title);
    $flat = collect($sections)->flatMap(fn ($s) => $s['items'])->values();
    $position = $flat->search(fn ($i) => $i['id'] === $item->id);
    $nextEntry = $neighbours['next'] ? $flat->firstWhere('id', $neighbours['next']) : null;
    $previousEntry = $neighbours['previous'] ? $flat->firstWhere('id', $neighbours['previous']) : null;
    $instructors = $course->instructors->where('status', 'active')->pluck('name')->implode(', ');
@endphp
<x-layouts.learner :title="$title" wide>
    <div class="space-y-5">
        @if($preview)
            <div class="rounded-card border-2 border-primary bg-assistant px-4 py-3 text-primary" role="status" data-preview-banner>
                <p class="font-bold">{{ __('learn.preview_banner') }}</p>
                @if($previewDraft)<p class="text-sm" data-preview-draft>{{ __('learn.preview_draft') }}</p>@endif
                @if($previewReason)<p class="text-sm" data-preview-reason>{{ __('learn.preview_would_lock', ['reason' => $previewReason]) }}</p>@endif
            </div>
        @endif
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <a href="{{ $backUrl ?? route('learner.courses') }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-primary"><x-ui.icon name="back" /> {{ $preview ? __('learn.back_to_curriculum') : __('learn.back_to_courses') }}</a>
                <h1 class="break-words text-3xl font-extrabold" data-course-title>{{ $course->title }}</h1>
                <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-muted">
                    @if($instructors !== '')<span class="inline-flex items-center gap-1"><x-ui.icon name="user" class="size-4" /> {{ __('learn.by_instructor', ['name' => $instructors]) }}</span>@endif
                    <span>{{ __('learn.lessons_count', ['count' => $flat->count()]) }}</span>
                </p>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] xl:grid-cols-[minmax(0,19rem)_minmax(0,1fr)]{{ $aiAvailable ? ' 2xl:grid-cols-[minmax(0,19rem)_minmax(0,1fr)_minmax(0,24rem)]' : '' }}">
            {{-- Curriculum (first column: right in Arabic, left in English) --}}
            <aside class="hidden space-y-3 lg:block" aria-label="{{ __('learn.curriculum') }}">
                @if($courseProgress)
                    <div class="space-y-2 rounded-card border border-border bg-surface p-4 shadow-card" data-course-progress>
                        <div class="flex items-center justify-between gap-2 font-bold">
                            <span>{{ __('learn.course_progress_label') }}</span>
                            <span data-course-percent>{{ $courseProgress->percent }}%</span>
                        </div>
                        <x-ui.progress-bar :label="__('learn.course_progress_label')" :value="$courseProgress->percent" />
                        <p class="text-sm text-muted">{{ __('learn.course_progress', ['done' => $courseProgress->completed_required, 'total' => $courseProgress->total_required]) }}</p>
                    </div>
                @endif
                <x-learning.curriculum-list :course="$course" :sections="$sections" :current="$item->id" :completed="$completed" :route="$showRoute" prefix="side" />
            </aside>

            {{-- Lesson --}}
            <div class="min-w-0 space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-muted">{{ __('learn.lesson_of', ['number' => ($position === false ? 0 : $position) + 1, 'total' => $flat->count()]) }}</p>
                        <h2 class="break-words text-3xl font-extrabold" data-lesson-title>{{ $title }}</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge tone="info">{{ __('learn.types.'.($lesson?->type ?? $item->itemable_type)) }}</x-ui.badge>
                        @if($lessonProgress?->status === 'completed')
                            <x-ui.badge tone="success" data-lesson-completed><x-ui.icon name="check" class="me-1 size-4" /> {{ __('learn.completed') }}</x-ui.badge>
                        @endif
                    </div>
                </div>

                @if(! $decision->allowed)
                    <x-ui.card :title="__('learn.locked_title')">
                        <p role="status" data-locked-reason>{{ $decision->message() }}</p>
                    </x-ui.card>
                @elseif($liveSession)
                    <x-learning.live-session-card :session="$liveSession" :state="$liveState" :replay-url="$replayUrl ?? null" :join-url="route('learn.join', ['course' => $course->id, 'item' => $item->id])" :viewer="$learner" />
                @elseif($quizOverview ?? null)
                    <x-learning.quiz-card :course="$course" :item="$item" :overview="$quizOverview" />
                @elseif($assignmentView ?? null)
                    <x-learning.assignment-card :course="$course" :item="$item" :view="$assignmentView" :viewer="$learner" />
                @elseif($lesson === null)
                    <x-ui.card><p>{{ __('learn.assessment_soon') }}</p></x-ui.card>
                @else
                    <section aria-label="{{ __('learn.now_playing') }}" data-player data-ai-available="{{ $aiAvailable ? 'true' : 'false' }}" class="space-y-4"
                        @if($entitled) data-progress-url="{{ route('learn.progress', ['course' => $course->id, 'item' => $item->id]) }}" data-complete-url="{{ route('learn.complete', ['course' => $course->id, 'item' => $item->id]) }}" data-resume="{{ $lessonProgress?->position_seconds ?? 0 }}" @endif>
                        @if($lesson->type === 'text' && ! empty($lesson->content))
                            <article class="rounded-card border border-border bg-surface p-5 shadow-card sm:p-8" data-lesson-text>
                                <x-learning.rich-content :content="$lesson->content" :lesson="$lesson" :viewer="$learner" />
                            </article>
                        @else
                        <div class="relative overflow-hidden rounded-card border border-border bg-black shadow-card" data-player-stage>
                            @if($descriptor === null)
                                <p class="bg-surface p-6">{{ __('learn.no_media') }}</p>
                            @else
                                <x-learning.lesson-media :descriptor="$descriptor" :title="$title" />
                                @if(in_array($descriptor->type, ['video', 'audio'], true) && \App\Domain\Media\Watermark::applies($descriptor->tier))
                                    <span class="pointer-events-none absolute end-3 top-3 select-none rounded bg-black/40 px-2 py-0.5 text-sm text-white/85 transition-all duration-700" data-watermark dir="ltr" aria-hidden="true">{{ \App\Domain\Media\Watermark::label($learner) }}</span>
                                @endif
                            @endif
                        </div>
                        @endif

                        @if($descriptor !== null && in_array($descriptor->type, ['video', 'audio'], true))
                            <div role="toolbar" aria-label="{{ __('learn.toolbar') }}" class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex items-center gap-2 rounded-full border border-border bg-surface px-3">
                                    <span class="font-semibold">{{ __('learn.speed') }}</span>
                                    <select data-player-speed class="min-h-11 bg-transparent px-1">
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
                                @if($canMarkComplete)
                                    <x-ui.button type="button" variant="secondary" data-player-complete>{{ __('learn.mark_complete') }}</x-ui.button>
                                @endif
                                @if($aiAvailable)
                                    <x-learning.assistant-opener />
                                @endif
                            </div>
                        @elseif($canMarkComplete)
                            <x-ui.button type="button" variant="secondary" data-player-complete>{{ __('learn.mark_complete') }}</x-ui.button>
                        @endif

                        @if($curriculumPdfUrl || $resources)
                            <x-ui.card class="p-3 sm:p-4">
                                <x-ui.tabs id="lesson-materials" class="lesson-materials" :label="__('learn.resources')" :items="array_filter(['pdf' => $curriculumPdfUrl ? __('learn.lesson_pdf') : null, 'attachments' => $resources ? __('learn.resources') : null])">
                                    <x-slot:pdf>
                                        @if($curriculumPdfUrl)
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex min-w-0 items-center gap-3">
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
                            <p class="text-sm text-muted">{{ __('learn.shortcuts') }}</p>
                        @endif
                    </section>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if($previousEntry)
                        <a href="{{ route($showRoute, ['course' => $course->id, 'item' => $previousEntry['id']]) }}" class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-border bg-surface px-4 font-semibold" rel="prev"><x-ui.icon name="back" /> {{ __('learn.previous') }}</a>
                    @else <span></span> @endif
                    @if($nextEntry)
                        <a href="{{ route($showRoute, ['course' => $course->id, 'item' => $nextEntry['id']]) }}" class="inline-flex min-h-12 max-w-full items-center gap-2 rounded-xl bg-primary px-5 font-semibold text-on-primary shadow-card" rel="next" data-next-lesson>
                            <span class="break-words">{{ __('learn.next_titled', ['title' => $nextEntry['title']]) }}</span> <x-ui.icon name="arrow" />
                        </a>
                    @endif
                </div>

                <div class="lg:hidden">
                    <x-ui.drawer id="curriculum-drawer" :title="__('learn.curriculum')" :trigger="__('learn.open_curriculum')">
                        <x-learning.curriculum-list :course="$course" :sections="$sections" :current="$item->id" :completed="$completed" :route="$showRoute" prefix="drawer" />
                    </x-ui.drawer>
                </div>
            </div>

            @if($aiAvailable && $lesson !== null)
                {{-- Lesson assistant (T-P7-03): rendered only for an eligible learner, so nothing exists in the page when AI is off. --}}
                <x-learning.assistant-panel :lesson="$lesson" :pdf-url="$curriculumPdfUrl" />
            @endif
        </div>
    </div>
</x-layouts.learner>
