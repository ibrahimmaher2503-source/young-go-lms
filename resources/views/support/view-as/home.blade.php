<x-layouts.base variant="auth" :title="__('support.view_as_title')" full>
    <x-support.view-as-banner :session="$session" />
    <div class="space-y-6" data-view-as-home>
        <h1 class="text-3xl font-bold">{{ __('support.view_as_courses_title', ['name' => $learner->display_name ?: $learner->name]) }}</h1>
        <p class="text-muted">{{ __('support.view_as_readonly') }}</p>
        @if($courses->isEmpty())
            <x-ui.card data-empty><p>{{ __('learner.no_courses') }}</p></x-ui.card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $row)
                    <x-ui.card data-course-status="{{ $row['status'] }}">
                        <h2 class="break-words text-lg font-bold"><a class="hover:underline" href="{{ route('support.view-as.course', ['course' => $row['course']->id]) }}">{{ $row['course']->title }}</a></h2>
                        <p class="mt-2 text-sm text-muted">{{ __('support.progress_status.'.$row['status']) }}@if($row['status'] !== 'expired') · <bdi>{{ $row['percent'] }}%</bdi> ({{ $row['done'] }} / {{ $row['total'] }})@endif</p>
                        @if($row['ends_at'])<p class="text-sm text-muted">{{ __('learner.ends_on', ['date' => $row['ends_at']->isoFormat('LL')]) }}</p>@endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.base>
