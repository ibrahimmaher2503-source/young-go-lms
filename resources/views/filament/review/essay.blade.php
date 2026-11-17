<x-filament-panels::page>
    @include('filament.review.styles')
    @php
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
        $text = fn (array $t) => ($t[app()->getLocale()] ?? '') !== '' ? $t[app()->getLocale()] : (string) collect($t)->first(fn ($v) => $v !== '', '');
    @endphp
    <div class="yg-rv" data-review-essay data-needs-review="{{ $attempt->needs_review ? 'true' : 'false' }}">
        <section class="yg-rv-card">
            <dl class="yg-rv-terms">
                <div><dt>{{ __('review.learner') }}</dt><dd>{{ $learner?->name }}</dd></div>
                <div><dt>{{ __('review.course') }}</dt><dd>{{ $course->title }}</dd></div>
                <div><dt>{{ __('review.attempt_no') }}</dt><dd>{{ $attempt->attempt_no }}</dd></div>
                <div><dt>{{ __('review.attempt_score') }}</dt><dd data-attempt-score>{{ $fmt($attempt->score) }} / {{ $fmt($attempt->total) }} ({{ $fmt($attempt->percent) }}%)</dd></div>
                <div><dt>{{ __('review.outcome') }}</dt><dd data-attempt-outcome>{{ $attempt->needs_review ? __('quiz.attempt.awaiting_review') : ($attempt->passed === true ? __('quiz.attempt.passed') : ($attempt->passed === false ? __('quiz.attempt.failed') : __('quiz.attempt.submitted_label'))) }}</dd></div>
            </dl>
        </section>

        @foreach($essays as $row)
            @php($q = $row['question'])
            @php($answer = $row['answer'])
            @php($written = trim((string) ($answer?->answer['text'] ?? '')) !== '')
            <section class="yg-rv-card" data-essay-question="{{ $q['id'] }}">
                <h3 class="yg-rv-title">{{ $text($q['prompt']) }}</h3>
                <p class="yg-rv-meta">{{ __('quiz.points_label', ['count' => $fmt($q['points'])]) }} · @if($answer?->points_awarded !== null)<span data-essay-points>{{ $fmt($answer->points_awarded) }} / {{ $fmt($q['points']) }}</span>@else{{ __('quiz.attempt.awaiting_review') }}@endif</p>
                <div class="yg-rv-text" dir="auto">{{ $written ? $answer->answer['text'] : __('quiz.attempt.no_answer') }}</div>
                @if(filled($answer?->feedback))<p class="yg-rv-text" data-essay-feedback>{{ $answer->feedback }}</p>@endif
                @if($written)
                    <div><button type="button" class="yg-rv-btn" wire:click="mountAction('grade', { question: {{ (int) $q['id'] }} })" data-grade-essay>{{ __('review.actions.grade_answer') }}</button></div>
                @endif
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
