{{-- Props: items (SubmissionView feedback list), zone (display time zone). Reviewer comments on a submission, oldest first. Empty renders nothing. --}}
@props(['items' => [], 'zone' => null])
@if(count($items) > 0)
    <div class="space-y-2 border-t border-border pt-4" data-feedback-thread>
        <h4 class="font-bold">{{ __('review.learner_view.feedback') }}</h4>
        <ol class="space-y-2">
            @foreach($items as $entry)
                <li class="space-y-1 rounded-xl border border-border px-3 py-2" data-feedback-item>
                    <p class="text-sm text-muted">
                        @if(filled($entry['author'])){{ $entry['author'] }} · @endif
                        @if($entry['revision'])
                            {{ __('assignment.revision_no', ['number' => $entry['revision']]) }} ·
                        @endif
                        <time datetime="{{ $entry['at']->toIso8601String() }}">{{ $entry['at']->copy()->timezone($zone ?: config('app.timezone'))->isoFormat('LLL') }}</time>
                    </p>
                    <p class="whitespace-pre-line break-words" dir="auto">{{ $entry['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
@endif
