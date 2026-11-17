{{-- Props: question (from QuizAttemptView::questions: no answer key), number, total. Inputs are named answers[id][selected][] or answers[id][text]. --}}
@props(['question', 'number', 'total'])
@php
    $id = $question['id'];
    $type = $question['type'];
    $given = (array) ($question['answer'] ?? []);
    $selected = array_map('intval', (array) ($given['selected'] ?? []));
    $text = (string) ($given['text'] ?? '');
    $prompt = \App\Domain\Assessment\Queries\QuizAttemptView::text($question['prompt']);
@endphp
<fieldset class="space-y-4" data-question="{{ $id }}" data-question-number="{{ $number }}" data-question-type="{{ $type }}" @if($number > 1) hidden @endif>
    <legend class="w-full space-y-1">
        <span class="block text-sm text-muted">{{ __('quiz.attempt.question_of', ['number' => $number, 'total' => $total]) }} · {{ __('quiz.points_label', ['count' => rtrim(rtrim(number_format($question['points'], 2), '0'), '.')]) }}</span>
        <span class="block break-words text-2xl font-bold">{{ $prompt }}</span>
        @if($type === 'multiple')<span class="block text-sm text-muted">{{ __('quiz.attempt.choose_many') }}</span>@endif
    </legend>

    @if(in_array($type, ['single', 'true_false'], true))
        <div class="space-y-3">
            @foreach($question['options'] as $option)
                <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-border px-4 py-3 has-[:checked]:border-primary has-[:checked]:bg-nav-active-bg has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary">
                    <input type="radio" class="size-5 accent-primary" name="answers[{{ $id }}][selected][]" value="{{ $option['key'] }}" @checked(in_array($option['key'], $selected, true)) data-answer-input>
                    <span class="break-words">{{ \App\Domain\Assessment\Queries\QuizAttemptView::text($option['text']) }}</span>
                </label>
            @endforeach
        </div>
    @elseif($type === 'multiple')
        <div class="space-y-3">
            @foreach($question['options'] as $option)
                <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-border px-4 py-3 has-[:checked]:border-primary has-[:checked]:bg-nav-active-bg has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary">
                    <input type="checkbox" class="size-5 accent-primary" name="answers[{{ $id }}][selected][]" value="{{ $option['key'] }}" @checked(in_array($option['key'], $selected, true)) data-answer-input>
                    <span class="break-words">{{ \App\Domain\Assessment\Queries\QuizAttemptView::text($option['text']) }}</span>
                </label>
            @endforeach
        </div>
    @elseif($type === 'short_answer')
        <label class="block space-y-2">
            <span class="sr-only">{{ __('quiz.your_answer') }}</span>
            <input type="text" maxlength="500" class="min-h-12 w-full rounded-xl border border-border bg-surface px-4" name="answers[{{ $id }}][text]" value="{{ $text }}" autocomplete="off" data-answer-input>
        </label>
    @else
        <label class="block space-y-2">
            <span class="sr-only">{{ __('quiz.your_answer') }}</span>
            <textarea rows="8" maxlength="{{ config('assessment.max_text_length') }}" class="w-full rounded-xl border border-border bg-surface p-4" name="answers[{{ $id }}][text]" data-answer-input>{{ $text }}</textarea>
        </label>
    @endif
</fieldset>
