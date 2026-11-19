{{-- Props: course, item, overview (QuizOverview::for). Rules, attempts left, start or resume, and past attempts. No questions and no keys here. --}}
@props(['course', 'item', 'overview'])
@php
    $quiz = $overview['quiz'];
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $title = \App\Domain\Assessment\Queries\QuizAttemptView::text($quiz->getTranslations('title'));
@endphp
<section class="space-y-5 rounded-card border border-border bg-surface p-5 shadow-card sm:p-8" aria-label="{{ $title }}" data-quiz-card>
    <div class="flex flex-wrap items-center gap-3">
        <span class="grid size-12 place-items-center rounded-full bg-nav-active-bg text-primary" aria-hidden="true"><x-ui.icon name="clipboard" class="size-6" /></span>
        <h3 class="break-words text-xl font-bold">{{ $title }}</h3>
    </div>
    @if(filled($quiz->description))<p class="text-muted">{{ \App\Domain\Assessment\Queries\QuizAttemptView::text($quiz->getTranslations('description')) }}</p>@endif

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-sm text-muted">{{ __('quiz.questions_count') }}</dt><dd class="text-xl font-bold" data-card-questions>{{ $overview['questions'] }}</dd></div>
        <div><dt class="text-sm text-muted">{{ __('quiz.total_marks') }}</dt><dd class="text-xl font-bold">{{ $fmt($overview['total_marks']) }}</dd></div>
        <div><dt class="text-sm text-muted">{{ __('quiz.time_limit') }}</dt><dd class="text-xl font-bold">{{ $quiz->time_limit_minutes ? __('curriculum.minutes', ['count' => $quiz->time_limit_minutes]) : __('quiz.none') }}</dd></div>
        <div><dt class="text-sm text-muted">{{ __('quiz.pass_mark') }}</dt><dd class="text-xl font-bold">{{ \App\Domain\Assessment\QuizPassMark::forQuiz($quiz) }}%</dd></div>
        <div><dt class="text-sm text-muted">{{ __('quiz.attempt.attempts_left') }}</dt><dd class="text-xl font-bold" data-card-attempts-left>{{ $overview['left'] === null ? __('quiz.unlimited') : $overview['left'] }}</dd></div>
    </dl>
    @if($quiz->progression === 'require_pass')<p class="font-semibold text-primary" data-require-pass>{{ __('quiz.attempt.require_pass_note') }}</p>@endif
    @if(session('quiz_error'))<p class="font-semibold text-danger" role="alert" data-quiz-error>{{ session('quiz_error') }}</p>@endif

    @if($overview['questions'] === 0)
        <p class="font-semibold text-muted" data-quiz-empty>{{ __('learn.assessment_soon') }}</p>
    @elseif($overview['can_start'])
        <form method="POST" action="{{ route('quiz.start', ['course' => $course->id, 'item' => $item->id]) }}">
            @csrf
            <button type="submit" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-5 font-bold text-on-primary" data-quiz-start>{{ $overview['open'] ? __('quiz.attempt.resume') : ($overview['used'] > 0 ? __('quiz.attempt.try_again') : __('quiz.attempt.start')) }}</button>
        </form>
    @else
        <p class="font-semibold text-muted" data-attempts-exhausted>{{ __('quiz.attempt.refused.attempts_exhausted') }}</p>
    @endif

    @if($overview['attempts']->isNotEmpty())
        <div class="space-y-2 border-t border-border pt-4">
            <h4 class="font-bold">{{ __('quiz.attempt.history') }}</h4>
            <ul class="space-y-2" data-attempt-history>
                @foreach($overview['attempts'] as $attempt)
                    @php($res = $attempt->isOpen() ? null : \App\Domain\Assessment\Queries\QuizAttemptView::result($attempt))
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border px-3 py-2">
                        <span class="font-semibold">{{ __('quiz.attempt.attempt_no', ['number' => $attempt->attempt_no]) }}</span>
                        <span class="text-sm text-muted">
                            @if($attempt->isOpen()) {{ __('quiz.attempt.in_progress') }}
                            @elseif($res['visible']) {{ $fmt($res['score']) }} / {{ $fmt($res['total']) }} ({{ $fmt($res['percent']) }}%)@if($res['needs_review']) · {{ __('quiz.attempt.awaiting_review') }}@elseif($res['passed'] === true) · {{ __('quiz.attempt.passed') }}@elseif($res['passed'] === false) · {{ __('quiz.attempt.failed') }}@endif
                            @else {{ __('quiz.attempt.submitted_label') }} @endif
                        </span>
                        <a href="{{ route('quiz.attempt', ['course' => $course->id, 'item' => $item->id, 'attempt' => $attempt->id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary">{{ $attempt->isOpen() ? __('quiz.attempt.resume') : __('quiz.attempt.view_result') }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
