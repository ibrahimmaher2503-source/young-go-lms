{{-- Props: remaining (seconds left, computed by the server; null when the quiz is untimed). The browser only counts down;
     the server decides when time is up and closes the attempt on its own. --}}
@props(['remaining' => null])
@if($remaining !== null)
    <div class="flex items-center gap-3" data-quiz-timer data-remaining="{{ $remaining }}" role="timer" aria-live="off">
        <span class="grid size-10 place-items-center rounded-xl bg-nav-active-bg text-primary" aria-hidden="true"><x-ui.icon name="clock" class="size-5" /></span>
        <span>
            <span class="block text-sm text-muted">{{ __('quiz.attempt.time_left') }}</span>
            <span class="block text-2xl font-extrabold tabular-nums" dir="ltr" data-timer-text>--:--</span>
        </span>
        <span class="sr-only" role="status" data-timer-announce></span>
    </div>
@else
    <div class="text-sm text-muted" data-quiz-untimed>{{ __('quiz.attempt.untimed') }}</div>
@endif
