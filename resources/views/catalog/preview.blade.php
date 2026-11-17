<x-layouts.base :title="$lesson->title" variant="public" wide>
    <div class="mx-auto max-w-4xl space-y-4" data-public-preview>
        <a href="{{ route('catalog.show', ['course' => $key]) }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-primary"><x-ui.icon name="back"/> {{ __('course_page.back_to_course', ['title' => $course->title]) }}</a>
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="break-words text-2xl font-extrabold" data-lesson-title>{{ $lesson->title }}</h1>
            <x-ui.badge tone="info">{{ __('course_page.free_preview') }}</x-ui.badge>
        </div>
        <div class="overflow-hidden rounded-card border border-border bg-black shadow-card" data-player-stage>
            @if($descriptor === null)
                <p class="bg-surface p-6">{{ __('learn.no_media') }}</p>
            @else
                <x-learning.lesson-media :descriptor="$descriptor" :title="$lesson->title"/>
            @endif
        </div>
        <x-ui.card :title="__('course_page.preview_note_title')"><p>{{ __('course_page.preview_note') }}</p></x-ui.card>
    </div>
</x-layouts.base>
