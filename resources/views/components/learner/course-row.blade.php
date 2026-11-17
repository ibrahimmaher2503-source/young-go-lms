{{-- Props: course (LearnerHomeController row: course, status, percent, done, total, continue, ends_on). Course card with cover, instructor and progress (C12/C13). --}}
@props(['course', 'certificate' => null])
@php($instructors = $course['course']->instructors->where('status', 'active')->pluck('name')->implode(', '))
<article class="flex min-w-0 flex-col gap-3 rounded-card border border-border bg-surface p-3 shadow-card" data-course-status="{{ $course['status'] }}">
    <x-learner.course-thumb :course="$course['course']" />
    <div class="min-w-0 space-y-1 px-1">
        <h3 class="break-words text-lg font-bold">@if($course['continue'])<a href="{{ route('learn.show', ['course' => $course['course']->id, 'item' => $course['continue']['item_id']]) }}" class="hover:underline">{{ $course['course']->title }}</a>@else{{ $course['course']->title }}@endif</h3>
        @if($instructors !== '')
            <p class="flex items-center gap-1 text-sm text-muted"><x-ui.icon name="user" class="size-4" /> <span class="break-words">{{ __('learner.with_instructor', ['name' => $instructors]) }}</span></p>
        @endif
    </div>
    @if($course['status'] !== 'expired')
        <div class="flex items-center gap-3 px-1" data-progress-row>
            <span class="w-12 shrink-0 text-sm font-semibold"><bdi>{{ $course['percent'] }}%</bdi></span>
            <x-ui.progress-bar class="flex-1" :label="__('learn.course_progress_label').': '.$course['course']->title" :value="$course['percent']" />
        </div>
    @endif
    @if($course['status'] !== 'in_progress')
        <div class="flex flex-wrap items-center gap-2 px-1">
            <x-ui.badge :tone="$course['status'] === 'completed' ? 'success' : 'warning'">{{ __('learner.filters.'.$course['status']) }}</x-ui.badge>
            @if($course['status'] === 'expired')
                <span class="text-sm text-muted">{{ __('learn.not_entitled_hint') }}</span>
            @elseif($course['continue'])
                <a href="{{ route('learn.show', ['course' => $course['course']->id, 'item' => $course['continue']['item_id']]) }}" class="ms-auto inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-surface px-4 font-semibold text-primary">{{ __('learner.review') }} <x-ui.icon name="arrow" class="size-4" /></a>
            @endif
        </div>
    @elseif($course['ends_at'])
        <p class="px-1 text-sm text-muted">{{ __('learner.ends_on', ['date' => $course['ends_at']->isoFormat('LL')]) }}</p>
    @endif
    @if($certificate)
        <div class="px-1" data-certificate-link><x-ui.link :href="route('certificates.download', $certificate->id)">{{ __('certificates.download') }}</x-ui.link></div>
    @endif
</article>
