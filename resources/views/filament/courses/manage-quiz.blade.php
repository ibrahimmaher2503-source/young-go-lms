<x-filament-panels::page>
    <section class="rounded-card border border-border bg-surface p-4 shadow-card" data-quiz-summary>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-muted">{{ __('quiz.questions_count') }}</dt><dd class="text-xl font-bold" data-question-count>{{ $questions->where('active', true)->count() }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('quiz.total_marks') }}</dt><dd class="text-xl font-bold" data-total-marks>{{ rtrim(rtrim(number_format($totalMarks, 2), '0'), '.') }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('quiz.pass_mark') }}</dt><dd class="text-xl font-bold">@php($passMark = \App\Domain\Assessment\QuizPassMark::forQuiz($quiz)){{ $passMark.'% ('.rtrim(rtrim(number_format($totalMarks * $passMark / 100, 2), '0'), '.').')' }}{{ $quiz->pass_mark_percent === null ? ' '.__('quiz.pass_mark_default_note') : '' }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('quiz.time_limit') }}</dt><dd class="text-xl font-bold">{{ $quiz->time_limit_minutes ? __('curriculum.minutes', ['count' => $quiz->time_limit_minutes]) : __('quiz.none') }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('quiz.max_attempts') }}</dt><dd class="text-xl font-bold">{{ $quiz->max_attempts ?? __('quiz.unlimited') }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('quiz.result_visibility') }}</dt><dd class="font-semibold">{{ __('quiz.visibility.'.$quiz->result_visibility) }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('quiz.progression') }}</dt><dd class="font-semibold">{{ __('quiz.progressions.'.$quiz->progression) }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('quiz.randomize') }}</dt><dd class="font-semibold">{{ $quiz->randomize ? __('quiz.yes') : __('quiz.no') }}</dd></div>
        </dl>
    </section>

    @if($previewing)
        <section class="space-y-4 rounded-card border-2 border-primary bg-surface p-5 shadow-card" data-quiz-preview aria-label="{{ __('quiz.preview') }}">
            <p class="font-bold">{{ __('quiz.preview_note') }}</p>
            @forelse($questions->where('active', true)->values() as $i => $question)
                <fieldset class="space-y-2 rounded-lg border border-border p-4" data-preview-question>
                    <legend class="px-1 font-bold">{{ $i + 1 }}. {{ $question->prompt }} <span class="text-sm font-normal text-muted">({{ __('quiz.points_label', ['count' => rtrim(rtrim((string) $question->points, '0'), '.')]) }})</span></legend>
                    @if(in_array($question->type, ['single', 'true_false'], true))
                        @foreach($question->options as $option)<label class="flex min-h-11 items-center gap-2"><input type="radio" disabled name="p{{ $question->id }}"> {{ $option->text }}</label>@endforeach
                    @elseif($question->type === 'multiple')
                        @foreach($question->options as $option)<label class="flex min-h-11 items-center gap-2"><input type="checkbox" disabled> {{ $option->text }}</label>@endforeach
                    @elseif($question->type === 'short_answer')
                        <input type="text" disabled class="w-full rounded-lg border border-input-border bg-input p-2" aria-label="{{ __('quiz.your_answer') }}">
                    @else
                        <textarea disabled rows="4" class="w-full rounded-lg border border-input-border bg-input p-2" aria-label="{{ __('quiz.your_answer') }}"></textarea>
                    @endif
                </fieldset>
            @empty
                <p class="text-muted">{{ __('quiz.empty') }}</p>
            @endforelse
        </section>
    @endif

    <section class="space-y-3" data-quiz-questions>
        @forelse($questions as $i => $question)
            <article class="rounded-card border border-border bg-surface p-4 shadow-card {{ $question->active ? '' : 'opacity-60' }}" data-question="{{ $question->id }}" data-question-active="{{ $question->active ? 'true' : 'false' }}" wire:key="q-{{ $question->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="flex flex-wrap items-center gap-2 text-sm text-muted">
                            <span class="font-bold">{{ $i + 1 }}</span>
                            <span class="rounded-full bg-page px-2 py-0.5 text-body">{{ __('quiz.types.'.$question->type) }}</span>
                            <span>{{ __('quiz.points_label', ['count' => rtrim(rtrim((string) $question->points, '0'), '.')]) }}</span>
                            <span>{{ __('quiz.version_label', ['version' => $question->version]) }}</span>
                            @unless($question->active)<strong>{{ __('quiz.removed') }}</strong>@endunless
                        </p>
                        <p class="break-words font-semibold">{{ $question->prompt }}</p>
                        @if($question->options->isNotEmpty())
                            <ul class="space-y-1 text-sm">
                                @foreach($question->options as $option)
                                    <li class="flex items-center gap-2" @if($option->is_correct || $question->type === 'short_answer') data-correct @endif>
                                        <span aria-hidden="true">{{ ($option->is_correct || $question->type === 'short_answer') ? '✓' : '○' }}</span>
                                        <span>{{ $option->text }}</span>
                                        @if($option->is_correct && $question->type !== 'short_answer')<span class="sr-only">{{ __('quiz.is_correct') }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @elseif($question->type === 'essay')
                            <p class="text-sm text-muted">{{ __('quiz.essay_hint') }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="min-h-11 rounded-lg border border-border bg-surface px-3 font-semibold hover:bg-hover" wire:click="mountAction('editQuestion', { question: {{ $question->id }} })">{{ __('quiz.edit') }}</button>
                        <button type="button" class="min-h-11 rounded-lg border border-border bg-surface px-3 hover:bg-hover" wire:click="moveQuestion({{ $question->id }}, 'up')" aria-label="{{ __('curriculum.move_up') }}">↑</button>
                        <button type="button" class="min-h-11 rounded-lg border border-border bg-surface px-3 hover:bg-hover" wire:click="moveQuestion({{ $question->id }}, 'down')" aria-label="{{ __('curriculum.move_down') }}">↓</button>
                        <button type="button" class="min-h-11 rounded-lg border border-border bg-surface px-3 font-semibold hover:bg-hover" wire:click="toggleQuestion({{ $question->id }})">{{ $question->active ? __('quiz.remove') : __('quiz.restore') }}</button>
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-card border border-border bg-surface p-6 text-center shadow-card" data-quiz-empty>{{ __('quiz.empty') }}</p>
        @endforelse
    </section>
    <x-filament-actions::modals />
</x-filament-panels::page>
