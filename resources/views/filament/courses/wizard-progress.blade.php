@php
    $completed = is_array($course?->wizard_completed ?? null) ? $course->wizard_completed : [];
    $steps = [1 => __('courses.step_basic'), 2 => __('courses.step_information'), 3 => __('courses.step_pricing')];
    $done = count(array_filter($steps, fn ($label, $number) => ! empty($completed[(string) $number]), ARRAY_FILTER_USE_BOTH));
@endphp
{{-- The Filament theme does not scan this folder, so the few rules needed live here and use the shared tokens. --}}
<style>
    .yg-wizard-progress { display: grid; gap: .75rem; }
    .yg-wizard-progress progress { appearance: none; inline-size: 100%; block-size: .75rem; overflow: hidden; border: 0; border-radius: var(--yg-radius-control); background: var(--yg-color-border); }
    .yg-wizard-progress progress::-webkit-progress-bar { background: var(--yg-color-border); }
    .yg-wizard-progress progress::-webkit-progress-value { background: var(--yg-color-primary); border-radius: var(--yg-radius-control); }
    .yg-wizard-progress progress::-moz-progress-bar { background: var(--yg-color-primary); border-radius: var(--yg-radius-control); }
    .yg-wizard-progress ul { display: grid; gap: .5rem; margin: 0; padding: 0; list-style: none; }
    .yg-wizard-progress li { display: flex; align-items: center; gap: .5rem; }
    .yg-wizard-progress li svg { width: 1.25rem; height: 1.25rem; flex: none; }
    .yg-wizard-progress .is-done svg { color: var(--yg-color-primary); }
    .yg-wizard-progress .is-todo svg { color: var(--yg-color-muted); }
    .yg-wizard-progress .yg-hidden-text { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
</style>
<div class="yg-wizard-progress">
    <p>{{ __('courses.steps_done', ['done' => $done, 'total' => count($steps)]) }}</p>
    <progress max="{{ count($steps) }}" value="{{ $done }}" aria-label="{{ __('courses.setup_progress') }}"></progress>
    <ul>
        @foreach($steps as $number => $label)
            @php($isDone = ! empty($completed[(string) $number]))
            <li class="{{ $isDone ? 'is-done' : 'is-todo' }}">
                <x-filament::icon :icon="$isDone ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle'" />
                <span>{{ $label }}</span>
                <span class="yg-hidden-text">{{ $isDone ? __('courses.step_done') : __('courses.step_todo') }}</span>
            </li>
        @endforeach
    </ul>
</div>
