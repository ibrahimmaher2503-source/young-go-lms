<x-layouts.base variant="auth" :title="$course->title" full>
    <x-support.view-as-banner :session="$session" />
    <div class="space-y-6" data-view-as-course>
        <a class="inline-flex min-h-11 items-center font-semibold text-primary" href="{{ route('support.view-as.home') }}">{{ __('support.view_as_back') }}</a>
        <h1 class="break-words text-3xl font-bold">{{ $course->title }}</h1>
        <p class="text-muted" data-access-decision="{{ $decision->allowed ? 'allowed' : 'denied' }}">{{ $decision->allowed ? __('support.view_as_has_access') : ($decision->message() ?? __('support.no')) }}</p>
        <p class="text-muted">{{ __('support.view_as_readonly') }}</p>

        @foreach($sections as $section)
            <x-ui.card :title="$section['title']">
                <ul class="space-y-2">
                    @foreach($section['items'] as $item)
                        <li class="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2" data-item-state="{{ $item['state'] }}">
                            <span class="break-words">{{ $item['title'] }}</span>
                            <span class="text-sm text-muted">{{ $item['state'] === 'locked' ? ($item['reason'] ?? __('support.locked')) : __('support.open') }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endforeach

        <x-ui.card :title="__('support.lesson_progress')">
            <ul class="space-y-2">
                @foreach($lessons as $lesson)
                    <li class="flex flex-wrap justify-between gap-2">
                        <span class="break-words">{{ $lesson['title'] }}</span>
                        <span class="text-sm text-muted">{{ __('courses.lesson_status.'.$lesson['status']) }} · <bdi>{{ $lesson['percent'] }}%</bdi>@if($lesson['last_at']) · {{ $lesson['last_at'] }}@endif</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
</x-layouts.base>
