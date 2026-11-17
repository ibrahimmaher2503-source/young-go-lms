@php
    use App\Domain\Assessment\Queries\QuizAttemptView;
    $quizTitle = QuizAttemptView::text($quiz->getTranslations('title'));
    $total = count($questions);
    $overviewUrl = route('learn.show', ['course' => $course->id, 'item' => $item->id]);
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<x-layouts.learner :title="$quizTitle" wide>
    <div class="space-y-5">
        <div class="space-y-1">
            <a href="{{ $overviewUrl }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-primary"><x-ui.icon name="back" /> {{ __('quiz.attempt.back_to_quiz') }}</a>
            <h1 class="break-words text-3xl font-extrabold" data-quiz-title>{{ __('quiz.attempt.title_prefix') }}: {{ $quizTitle }}</h1>
        </div>

        @if(session('quiz_notice'))<div class="rounded-card border border-primary bg-assistant px-4 py-3 font-semibold text-primary" role="status" data-quiz-notice>{{ session('quiz_notice') }}</div>@endif
        @if(session('quiz_error'))<div class="rounded-card border border-danger px-4 py-3 font-semibold text-danger" role="alert" data-quiz-error>{{ session('quiz_error') }}</div>@endif

        @if($open)
            <form method="POST" action="{{ route('quiz.submit', ['course' => $course->id, 'item' => $item->id, 'attempt' => $attempt->id]) }}" class="space-y-5"
                data-quiz-form data-save-url="{{ route('quiz.save', ['course' => $course->id, 'item' => $item->id, 'attempt' => $attempt->id]) }}" data-total="{{ $total }}"
                data-text-saved="{{ __('quiz.attempt.saved') }}" data-text-saving="{{ __('quiz.attempt.saving') }}" data-text-save-failed="{{ __('quiz.attempt.save_failed') }}"
                data-text-time-up="{{ __('quiz.attempt.time_up') }}" data-text-minute-left="{{ __('quiz.attempt.minute_left') }}">
                @csrf
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 rounded-card border border-border bg-surface p-4 shadow-card">
                    <div class="min-w-48 flex-1 space-y-2">
                        <p class="font-bold" data-progress-label>{{ __('quiz.attempt.question_of', ['number' => 1, 'total' => $total]) }}</p>
                        <div class="h-2 overflow-hidden rounded-full bg-border" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="1" aria-label="{{ __('quiz.attempt.progress') }}" data-progressbar><div class="h-full rounded-full bg-primary" style="width: {{ $total > 0 ? round(100 / $total) : 0 }}%" data-progressfill></div></div>
                    </div>
                    <x-learning.quiz-timer :remaining="$remaining" />
                    <p class="rounded-xl bg-nav-active-bg px-3 py-2 text-sm font-semibold text-primary" role="status" aria-live="polite" data-save-state>{{ __('quiz.attempt.autosave') }}</p>
                </div>

                <div class="grid gap-5 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)]">
                    <aside class="space-y-4" aria-label="{{ __('quiz.attempt.navigator') }}">
                        <div class="rounded-card border border-border bg-surface p-4 shadow-card">
                            <h2 class="pb-3 text-lg font-bold">{{ __('quiz.attempt.navigator') }}</h2>
                            <ol class="grid grid-cols-5 gap-2" data-navigator>
                                @foreach($questions as $index => $question)
                                    <li><button type="button" class="grid min-h-11 w-full place-items-center rounded-xl border border-border font-bold aria-[current=true]:border-primary aria-[current=true]:bg-primary aria-[current=true]:text-on-primary data-[answered=true]:bg-nav-active-bg" data-goto="{{ $index + 1 }}" data-answered="false" @if($index === 0) aria-current="true" @endif aria-label="{{ __('quiz.attempt.go_to_question', ['number' => $index + 1]) }}">{{ $index + 1 }}</button></li>
                                @endforeach
                            </ol>
                            <ul class="mt-4 space-y-1 border-t border-border pt-3 text-sm text-muted">
                                <li class="flex items-center gap-2"><span class="inline-block size-4 rounded bg-nav-active-bg"></span> {{ __('quiz.attempt.legend_answered') }}</li>
                                <li class="flex items-center gap-2"><span class="inline-block size-4 rounded bg-primary"></span> {{ __('quiz.attempt.legend_current') }}</li>
                                <li class="flex items-center gap-2"><span class="inline-block size-4 rounded border border-border"></span> {{ __('quiz.attempt.legend_unanswered') }}</li>
                            </ul>
                        </div>
                    </aside>

                    <section class="space-y-6 rounded-card border border-border bg-surface p-5 shadow-card sm:p-8" aria-label="{{ __('quiz.attempt.questions') }}">
                        @foreach($questions as $index => $question)
                            <x-learning.quiz-question :question="$question" :number="$index + 1" :total="$total" />
                        @endforeach

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                            <button type="button" class="inline-flex min-h-12 items-center rounded-xl border border-border px-5 font-semibold" data-finish>{{ __('quiz.attempt.finish') }}</button>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-border px-5 font-semibold" data-prev><x-ui.icon name="back" class="size-4" /> {{ __('quiz.attempt.previous') }}</button>
                                <button type="button" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-5 font-bold text-on-primary" data-next>{{ __('quiz.attempt.next') }}</button>
                            </div>
                        </div>
                        <div class="hidden space-y-3 rounded-xl border-2 border-primary bg-assistant p-4" data-finish-confirm role="alertdialog" aria-labelledby="finish-title">
                            <p id="finish-title" class="font-bold" data-finish-text data-unanswered="{{ __('quiz.attempt.confirm_finish_missing', ['count' => ':count']) }}">{{ __('quiz.attempt.confirm_finish') }}</p>
                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="inline-flex min-h-12 items-center rounded-xl bg-primary px-5 font-bold text-on-primary" data-submit>{{ __('quiz.attempt.submit') }}</button>
                                <button type="button" class="inline-flex min-h-12 items-center rounded-xl border border-border px-5 font-semibold" data-keep-working>{{ __('quiz.attempt.keep_working') }}</button>
                            </div>
                        </div>
                    </section>
                </div>
            </form>
        @else
            @php($res = $result)
            <section class="space-y-4 rounded-card border border-border bg-surface p-5 shadow-card sm:p-8" aria-label="{{ __('quiz.attempt.result') }}" data-quiz-result data-result-visible="{{ $res['visible'] ? 'true' : 'false' }}">
                <h2 class="text-2xl font-bold">{{ __('quiz.attempt.result') }} · {{ __('quiz.attempt.attempt_no', ['number' => $attempt->attempt_no]) }}</h2>
                @if($res['submitted_by'] === 'auto')<p class="font-semibold text-muted" data-auto-submitted>{{ __('quiz.attempt.auto_submitted') }}</p>@endif
                @if(! $res['visible'])
                    <p class="text-lg" data-result-hidden>{{ __('quiz.attempt.result_hidden') }}</p>
                @else
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div><dt class="text-sm text-muted">{{ __('quiz.attempt.score') }}</dt><dd class="text-3xl font-extrabold" data-score>{{ $fmt($res['score']) }} / {{ $fmt($res['total']) }}</dd></div>
                        <div><dt class="text-sm text-muted">{{ __('quiz.attempt.percent') }}</dt><dd class="text-3xl font-extrabold" data-percent>{{ $fmt($res['percent']) }}%</dd></div>
                        <div><dt class="text-sm text-muted">{{ __('quiz.attempt.outcome') }}</dt>
                            <dd class="text-xl font-bold" data-outcome>
                                @if($res['needs_review']) <x-ui.badge tone="warning">{{ __('quiz.attempt.awaiting_review') }}</x-ui.badge>
                                @elseif($res['passed'] === true) <x-ui.badge tone="success">{{ __('quiz.attempt.passed') }}</x-ui.badge>
                                @elseif($res['passed'] === false) <x-ui.badge tone="danger">{{ __('quiz.attempt.failed') }}</x-ui.badge>
                                @else <x-ui.badge>{{ __('quiz.attempt.submitted_label') }}</x-ui.badge> @endif
                            </dd>
                        </div>
                    </dl>
                    @if($res['needs_review'])<p class="text-muted">{{ __('quiz.attempt.review_note') }}</p>@endif
                @endif
            </section>

            @if($res['questions'] !== [])
                <section class="space-y-4" aria-label="{{ __('quiz.attempt.review') }}" data-quiz-review>
                    @foreach($res['questions'] as $i => $q)
                        <article class="space-y-3 rounded-card border border-border bg-surface p-5 shadow-card" data-review-question="{{ $q['id'] }}" data-review-correct="{{ $q['is_correct'] === null ? 'pending' : ($q['is_correct'] ? 'true' : 'false') }}">
                            <p class="text-sm text-muted">{{ $i + 1 }} · {{ $q['points_awarded'] === null ? __('quiz.attempt.awaiting_review') : $fmt($q['points_awarded']).' / '.$fmt($q['points']) }}</p>
                            <p class="break-words text-lg font-bold">{{ QuizAttemptView::text($q['prompt']) }}</p>
                            @php($given = (array) ($q['answer'] ?? []))
                            @if($q['options'] !== [])
                                <ul class="space-y-2">
                                    @foreach($q['options'] as $o)
                                        @php($chosen = in_array($o['key'], array_map('intval', (array) ($given['selected'] ?? [])), true))
                                        <li class="flex items-center gap-2 rounded-xl border px-3 py-2 {{ $o['is_correct'] ? 'border-success' : 'border-border' }}" @if($o['is_correct']) data-correct-option @endif>
                                            <span aria-hidden="true">{{ $chosen ? '●' : '○' }}</span>
                                            <span class="break-words">{{ QuizAttemptView::text($o['text']) }}</span>
                                            @if($chosen)<x-ui.badge>{{ __('quiz.attempt.your_choice') }}</x-ui.badge>@endif
                                            @if($o['is_correct'])<x-ui.badge tone="success">{{ __('quiz.is_correct') }}</x-ui.badge>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="rounded-xl bg-bg px-3 py-2"><span class="text-sm text-muted">{{ __('quiz.attempt.your_answer') }}:</span> {{ $given['text'] ?? __('quiz.attempt.no_answer') }}</p>
                                @if($q['accepted'] !== [])<p class="text-sm"><span class="text-muted">{{ __('quiz.accepted_answers') }}:</span> {{ collect($q['accepted'])->map(fn ($t) => QuizAttemptView::text($t))->implode('، ') }}</p>@endif
                            @endif
                            @if(filled($q['feedback']))<p class="rounded-xl border border-border px-3 py-2" data-essay-feedback><span class="text-sm text-muted">{{ __('review.learner_view.instructor_feedback') }}:</span> {{ $q['feedback'] }}</p>@endif
                            @if(filled($q['explanation']))<p class="rounded-xl bg-nav-active-bg px-3 py-2" data-explanation>{{ QuizAttemptView::text($q['explanation']) }}</p>@endif
                        </article>
                    @endforeach
                </section>
            @endif
        @endif
    </div>
</x-layouts.learner>
