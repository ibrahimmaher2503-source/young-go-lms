@php
    $completed = is_array($course?->wizard_completed ?? null) ? $course->wizard_completed : [];
    $steps = [1 => __('courses.step_basic'), 2 => __('courses.step_information'), 3 => __('courses.step_pricing'), 4 => __('course_wizard.step_media'), 5 => __('course_wizard.step_seo'), 6 => __('course_wizard.step_custom'), 7 => __('course_wizard.step_finish')];
    $done = count(array_filter($steps, fn ($label, $number) => ! empty($completed[(string) $number]), ARRAY_FILTER_USE_BOTH));
@endphp
<div class="yg-wizard-progress">
    <p>{{ __('courses.steps_done', ['done' => $done, 'total' => count($steps)]) }}</p>
    <progress max="{{ count($steps) }}" value="{{ $done }}" aria-label="{{ __('courses.setup_progress') }}"></progress>
    <ul>
        @foreach($steps as $number => $label)
            @php($isDone = ! empty($completed[(string) $number]))
            <li class="{{ $isDone ? 'is-done' : 'is-todo' }}">
                <x-filament::icon :icon="$isDone ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle'" />
                <span>{{ $label }}</span>
                <span class="fi-sr-only">{{ $isDone ? __('courses.step_done') : __('courses.step_todo') }}</span>
            </li>
        @endforeach
    </ul>
</div>
