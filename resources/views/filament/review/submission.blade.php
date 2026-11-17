<x-filament-panels::page>
    @include('filament.review.styles')
    @php
        $zone = auth()->user()?->timezone;
        $zone = is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : config('app.timezone');
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    @endphp
    <div class="yg-rv" data-review-submission data-submission-status="{{ $submission->status }}">
        <section class="yg-rv-card" aria-label="{{ __('review.summary_title') }}">
            <dl class="yg-rv-terms">
                <div><dt>{{ __('review.learner') }}</dt><dd>{{ $submission->learner?->name }}</dd></div>
                <div><dt>{{ __('review.course') }}</dt><dd>{{ $course->title }}</dd></div>
                <div><dt>{{ __('review.status') }}</dt><dd><span class="yg-rv-badge" data-review-status>{{ __('assignment.statuses.'.$submission->status) }}</span></dd></div>
                <div><dt>{{ __('review.due') }}</dt><dd>{{ $assignment->due_at?->copy()->timezone($zone)->isoFormat('LLL') ?? __('assignment.no_due_date') }}</dd></div>
                <div><dt>{{ __('review.late_policy') }}</dt><dd>{{ __('assignment.late_policies.'.$assignment->late_policy, ['percent' => $assignment->late_penalty_percent]) }}</dd></div>
                <div><dt>{{ __('review.revisions_left') }}</dt><dd>{{ $revisionsLeft }}</dd></div>
            </dl>
        </section>

        <div class="yg-rv-grid">
            <div class="yg-rv">
                @forelse($revisions->reverse() as $revision)
                    <section class="yg-rv-card" data-review-revision="{{ $revision->number }}">
                        <h3 class="yg-rv-title">{{ __('assignment.revision_no', ['number' => $revision->number]) }}
                            @if($revision->number === $submission->current_revision)<span class="yg-rv-badge">{{ __('review.current') }}</span>@endif
                            @if($revision->is_late)<span class="yg-rv-badge" data-review-late>{{ $revision->late_penalty_percent ? __('assignment.late_penalty', ['percent' => $revision->late_penalty_percent]) : __('assignment.late') }}</span>@endif
                        </h3>
                        <p class="yg-rv-meta"><time datetime="{{ $revision->submitted_at->toIso8601String() }}">{{ $revision->submitted_at->copy()->timezone($zone)->isoFormat('LLL') }}</time></p>
                        @if(filled($revision->text_body))<div class="yg-rv-text" dir="auto">{{ $revision->text_body }}</div>@endif
                        @if($revision->files->isNotEmpty())
                            <ul class="yg-rv-list">
                                @foreach($revision->files as $file)
                                    <li class="yg-rv-item"><a class="yg-rv-link" href="{{ route('assignment.file', ['file' => $file->id]) }}" target="_blank" rel="noopener">{{ $file->display_name }}</a>
                                        <span class="yg-rv-meta">{{ __('assignment.types.'.$file->kind) }} · {{ \Illuminate\Support\Number::fileSize($file->size, 1) }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @empty
                    <section class="yg-rv-card"><p class="yg-rv-meta">{{ __('review.nothing_handed_in') }}</p></section>
                @endforelse
            </div>

            <div class="yg-rv">
                <section class="yg-rv-card" aria-label="{{ __('review.decisions') }}">
                    <h3 class="yg-rv-title">{{ __('review.decisions') }}</h3>
                    <ol class="yg-rv-list" data-review-decisions>
                        @forelse($decisions->reverse() as $decision)
                            <li class="yg-rv-item">
                                <strong>{{ __('review.decision_label.'.$decision->decision) }}@if($decision->final_score !== null) · {{ $fmt($decision->final_score) }}/100 @if($decision->late_penalty_percent) ({{ __('assignment.late_penalty', ['percent' => $decision->late_penalty_percent]) }}, {{ __('review.entered') }} {{ $fmt($decision->score) }})@endif @endif</strong>
                                <span class="yg-rv-meta">{{ $decision->reviewer?->name }} · {{ __('assignment.revision_no', ['number' => $decision->revision_number]) }} · {{ $decision->created_at->copy()->timezone($zone)->isoFormat('LLL') }}</span>
                            </li>
                        @empty
                            <li class="yg-rv-meta">{{ __('review.no_decisions') }}</li>
                        @endforelse
                    </ol>
                </section>

                <section class="yg-rv-card" aria-label="{{ __('review.thread') }}">
                    <h3 class="yg-rv-title">{{ __('review.thread') }}</h3>
                    <ol class="yg-rv-list" data-review-thread>
                        @forelse($feedback as $entry)
                            <li class="yg-rv-item" data-feedback-item>
                                <span class="yg-rv-meta">{{ $entry->author?->name }} @if($entry->revision_number)· {{ __('assignment.revision_no', ['number' => $entry->revision_number]) }} @endif· {{ $entry->created_at->copy()->timezone($zone)->isoFormat('LLL') }}</span>
                                <span class="yg-rv-text" dir="auto">{{ $entry->body }}</span>
                            </li>
                        @empty
                            <li class="yg-rv-meta">{{ __('review.no_feedback') }}</li>
                        @endforelse
                    </ol>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
