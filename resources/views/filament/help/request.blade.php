<x-filament-panels::page>
    @include('filament.review.styles')
    @php
        $zone = auth()->user()?->timezone;
        $zone = is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : config('app.timezone');
        $seconds = $helpRequest->video_position;
        $clock = $seconds === null ? null : (intdiv($seconds, 3600) > 0 ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60) : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60));
        $item = $helpRequest->lesson->item;
    @endphp
    <div class="yg-rv" data-help-request data-help-status="{{ $helpRequest->status->value }}">
        <section class="yg-rv-card" aria-label="{{ __('help.queue.details') }}">
            <dl class="yg-rv-terms">
                <div><dt>{{ __('help.queue.student') }}</dt><dd>{{ $helpRequest->student?->name }}</dd></div>
                <div><dt>{{ __('help.queue.course') }}</dt><dd>{{ $helpRequest->course->title }}</dd></div>
                <div><dt>{{ __('help.queue.lesson') }}</dt><dd>{{ $helpRequest->lesson->title }}</dd></div>
                <div><dt>{{ __('help.queue.status') }}</dt><dd><span class="yg-rv-badge" data-help-status-badge>{{ $helpRequest->status->label() }}</span>
                    @if($helpRequest->isOverdue())<span class="yg-rv-badge" data-help-overdue>{{ __('help.queue.overdue_badge') }}</span>@endif</dd></div>
                <div><dt>{{ __('help.queue.instructor') }}</dt><dd>{{ $helpRequest->instructor?->name ?? __('help.queue.admin_queue') }}</dd></div>
                <div><dt>{{ __('help.queue.due') }}</dt><dd>{{ $helpRequest->due_at?->copy()->timezone($zone)->isoFormat('LLL') ?? '-' }}</dd></div>
                <div><dt>{{ __('help.queue.video_position') }}</dt><dd>
                    @if($clock !== null)
                        <bdi>{{ $clock }}</bdi>
                        @if($item !== null)<a class="yg-rv-link" href="{{ route('learn.preview', ['course' => $helpRequest->course_id, 'item' => $item->getKey()]) }}" target="_blank" rel="noopener">{{ __('help.queue.open_lesson') }}</a>@endif
                    @else - @endif</dd></div>
                <div><dt>{{ __('help.queue.source_pages') }}</dt><dd>
                    @forelse($helpRequest->source_pages ?? [] as $page)<span class="yg-rv-badge" data-help-page>{{ __('ai.source_page', ['page' => $page]) }}</span> @empty - @endforelse</dd></div>
                <div><dt>{{ __('help.queue.document_version') }}</dt><dd><bdi>{{ $helpRequest->document_version }}</bdi></dd></div>
            </dl>
        </section>

        @if($canSeeFlags && $flags->isNotEmpty())
            <section class="yg-rv-card" aria-label="{{ __('help.queue.safeguarding') }}" data-help-safeguarding>
                <h3 class="yg-rv-title">{{ __('help.queue.safeguarding') }}</h3>
                <ul class="yg-rv-list">
                    @foreach($flags as $flag)
                        <li class="yg-rv-item">{{ __('help.queue.flag_category.'.$flag->category) !== 'help.queue.flag_category.'.$flag->category ? __('help.queue.flag_category.'.$flag->category) : $flag->category }} · {{ __('help.queue.flag_status.'.$flag->status) !== 'help.queue.flag_status.'.$flag->status ? __('help.queue.flag_status.'.$flag->status) : $flag->status }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="yg-rv-grid">
            <div class="yg-rv">
                <section class="yg-rv-card" aria-label="{{ __('help.queue.context') }}" data-help-context>
                    <h3 class="yg-rv-title">{{ __('help.queue.question') }}</h3>
                    <div class="yg-rv-text" dir="auto">{{ $helpRequest->question }}</div>
                    <h3 class="yg-rv-title">{{ __('help.queue.ai_answer') }} <span class="yg-rv-meta">({{ __('help.queue.ai_status.'.($helpRequest->ai_status ?? 'answered')) }})</span></h3>
                    <div class="yg-rv-text" dir="auto">{{ $helpRequest->ai_answer }}</div>
                </section>
            </div>
            <div class="yg-rv">
                <section class="yg-rv-card" aria-label="{{ __('help.queue.thread') }}">
                    <h3 class="yg-rv-title">{{ __('help.queue.thread') }}</h3>
                    <ol class="yg-rv-list" data-help-thread>
                        @forelse($helpRequest->messages as $entry)
                            <li class="yg-rv-item" data-help-message="{{ $entry->role }}">
                                <span class="yg-rv-meta">{{ $entry->author?->name }} · {{ __('help.thread.'.($entry->role === 'instructor' ? 'instructor' : 'student')) }} · {{ $entry->created_at->copy()->timezone($zone)->isoFormat('LLL') }}</span>
                                <span class="yg-rv-text" dir="auto">{{ $entry->body }}</span>
                            </li>
                        @empty
                            <li class="yg-rv-meta">{{ __('help.thread.no_replies') }}</li>
                        @endforelse
                    </ol>
                </section>
                <section class="yg-rv-card" aria-label="{{ __('help.queue.history') }}">
                    <h3 class="yg-rv-title">{{ __('help.queue.history') }}</h3>
                    <ol class="yg-rv-list" data-help-events>
                        @foreach($helpRequest->events->reverse() as $event)
                            <li class="yg-rv-item"><strong>{{ __('help.events.'.$event->event) }}@if($event->to_status && $event->to_status !== $event->from_status) · {{ __('help.statuses.'.$event->to_status) }}@endif</strong>
                                <span class="yg-rv-meta">{{ $event->actor?->name ?? __('help.events.system') }} · {{ $event->created_at->copy()->timezone($zone)->isoFormat('LLL') }}</span></li>
                        @endforeach
                    </ol>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
