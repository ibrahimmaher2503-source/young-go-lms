<x-layouts.base :title="$lesson->title" variant="public" wide>
    <div class="mx-auto max-w-4xl space-y-5" data-public-preview>
        <a href="{{ route('catalog.show', ['course' => $key]) }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-primary"><x-ui.icon name="back"/> {{ __('course_page.back_to_course', ['title' => $course->title]) }}</a>

        <div class="space-y-4 rounded-card border border-border bg-surface p-4 shadow-card sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0 space-y-1">
                    <p class="text-sm text-muted">{{ $course->title }}</p>
                    <h1 class="break-words text-2xl font-extrabold" data-lesson-title>{{ $lesson->title }}</h1>
                </div>
                <x-ui.badge tone="info">{{ __('course_page.free_preview') }}</x-ui.badge>
            </div>

            <div class="overflow-hidden rounded-card border border-border bg-media" data-player-stage>
                @if($lesson->type === 'text' && ! empty($lesson->content))
                    <div class="bg-surface p-5 sm:p-8" data-lesson-text>
                        <x-learning.rich-content :content="$lesson->content" :lesson="$lesson" :viewer="$viewer ?? null" />
                    </div>
                @elseif($descriptor === null)
                    <p class="bg-surface p-6">{{ __('learn.no_media') }}</p>
                @else
                    <x-learning.lesson-media :descriptor="$descriptor" :title="$lesson->title"/>
                @endif
            </div>
        </div>

        @if(! empty($resources))
            <x-ui.card :title="__('learn.resources')">
                <ul class="space-y-2">
                    @foreach($resources as $resource)
                        <li>
                            <x-ui.link :href="$resource['url']" class="w-full justify-between gap-2 border border-border bg-page/50 px-3 no-underline hover:bg-page">
                                <span class="break-words">{{ __('learn.open_file', ['name' => $resource['title']]) }}</span>
                                <x-ui.icon name="chevron" />
                            </x-ui.link>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card :title="__('course_page.preview_note_title')">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-muted">{{ __('course_page.preview_note') }}</p>
                <x-ui.link :href="route('catalog.show', ['course' => $key])" class="bg-primary px-4 text-on-primary no-underline hover:opacity-95">{{ __('course_page.back_to_course', ['title' => $course->title]) }}</x-ui.link>
            </div>
        </x-ui.card>
    </div>
</x-layouts.base>
