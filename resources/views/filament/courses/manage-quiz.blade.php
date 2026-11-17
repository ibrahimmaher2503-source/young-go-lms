<x-filament-panels::page>
    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900" data-quiz-summary>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.questions_count') }}</dt><dd class="text-xl font-bold" data-question-count>{{ $questions->where('active', true)->count() }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.total_marks') }}</dt><dd class="text-xl font-bold" data-total-marks>{{ rtrim(rtrim(number_format($totalMarks, 2), '0'), '.') }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.pass_mark') }}</dt><dd class="text-xl font-bold">{{ $quiz->pass_mark_percent ? $quiz->pass_mark_percent.'% ('.rtrim(rtrim(number_format($totalMarks * $quiz->pass_mark_percent / 100, 2), '0'), '.').')' : __('quiz.none') }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.time_limit') }}</dt><dd class="text-xl font-bold">{{ $quiz->time_limit_minutes ? __('curriculum.minutes', ['count' => $quiz->time_limit_minutes]) : __('quiz.none') }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.max_attempts') }}</dt><dd class="text-xl font-bold">{{ $quiz->max_attempts ?? __('quiz.unlimited') }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.result_visibility') }}</dt><dd class="font-semibold">{{ __('quiz.visibility.'.$quiz->result_visibility) }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.progression') }}</dt><dd class="font-semibold">{{ __('quiz.progressions.'.$quiz->progression) }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.randomize') }}</dt><dd class="font-semibold">{{ $quiz->randomize ? __('quiz.yes') : __('quiz.no') }}</dd></div>
        </dl>
    </section>

    @if($previewing)
        <section class="space-y-4 rounded-xl border-2 border-primary-600 bg-white p-5 dark:bg-gray-900" data-quiz-preview aria-label="{{ __('quiz.preview') }}">
            <p class="font-bold">{{ __('quiz.preview_note') }}</p>
            @forelse($questions->where('active', true)->values() as $i => $question)
                <fieldset class="space-y-2 rounded-lg border border-gray-200 p-4 dark:border-white/10" data-preview-question>
                    <legend class="px-1 font-bold">{{ $i + 1 }}. {{ $question->prompt }} <span class="text-sm font-normal text-gray-600 dark:text-gray-300">({{ __('quiz.points_label', ['count' => rtrim(rtrim((string) $question->points, '0'), '.')]) }})</span></legend>
                    @if(in_array($question->type, ['single', 'true_false'], true))
                        @foreach($question->options as $option)<label class="flex min-h-11 items-center gap-2"><input type="radio" disabled name="p{{ $question->id }}"> {{ $option->text }}</label>@endforeach
                    @elseif($question->type === 'multiple')
                        @foreach($question->options as $option)<label class="flex min-h-11 items-center gap-2"><input type="checkbox" disabled> {{ $option->text }}</label>@endforeach
                    @elseif($question->type === 'short_answer')
                        <input type="text" disabled class="w-full rounded-lg border border-gray-300 p-2 dark:border-white/20" aria-label="{{ __('quiz.your_answer') }}">
                    @else
                        <textarea disabled rows="4" class="w-full rounded-lg border border-gray-300 p-2 dark:border-white/20" aria-label="{{ __('quiz.your_answer') }}"></textarea>
                    @endif
                </fieldset>
            @empty
                <p class="text-gray-600 dark:text-gray-300">{{ __('quiz.empty') }}</p>
            @endforelse
        </section>
    @endif

    <section class="space-y-3" data-quiz-questions>
        @forelse($questions as $i => $question)
            <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 {{ $question->active ? '' : 'opacity-60' }}" data-question="{{ $question->id }}" data-question-active="{{ $question->active ? 'true' : 'false' }}" wire:key="q-{{ $question->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-bold">{{ $i + 1 }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-white/10">{{ __('quiz.types.'.$question->type) }}</span>
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
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('quiz.essay_hint') }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="min-h-11 rounded-lg border border-gray-300 px-3 font-semibold dark:border-white/20" wire:click="mountAction('editQuestion', { question: {{ $question->id }} })">{{ __('quiz.edit') }}</button>
                        <button type="button" class="min-h-11 rounded-lg border border-gray-300 px-3 dark:border-white/20" wire:click="moveQuestion({{ $question->id }}, 'up')" aria-label="{{ __('curriculum.move_up') }}">↑</button>
                        <button type="button" class="min-h-11 rounded-lg border border-gray-300 px-3 dark:border-white/20" wire:click="moveQuestion({{ $question->id }}, 'down')" aria-label="{{ __('curriculum.move_down') }}">↓</button>
                        <button type="button" class="min-h-11 rounded-lg border border-gray-300 px-3 font-semibold dark:border-white/20" wire:click="toggleQuestion({{ $question->id }})">{{ $question->active ? __('quiz.remove') : __('quiz.restore') }}</button>
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-gray-200 bg-white p-6 text-center dark:border-white/10 dark:bg-gray-900" data-quiz-empty>{{ __('quiz.empty') }}</p>
        @endforelse
    </section>
    <x-filament-actions::modals />
</x-filament-panels::page>
