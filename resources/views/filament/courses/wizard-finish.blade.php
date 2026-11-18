@php
    use App\Domain\Catalog\CourseReadiness;
    use App\Domain\Catalog\CourseStatus;
    use App\Domain\Seo\PublicUrl;
    use App\Filament\Resources\Courses\CourseResource;

    $saved = $course instanceof \App\Models\Course && $course->exists;
    $checks = $saved ? CourseReadiness::checks($course) : [];
    $stepLabels = [1 => __('courses.step_basic'), 3 => __('courses.step_pricing'), 4 => __('course_wizard.step_media'), 5 => __('course_wizard.step_seo'), 6 => __('course_wizard.step_custom')];
@endphp
<div class="yg-wizard-finish">
    @if (! $saved)
        <p>{{ __('course_wizard.finish_save_first') }}</p>
    @else
        <p>
            <strong>{{ __('course_wizard.finish_status') }}:</strong>
            {{ __('courses.'.$course->status->value) }}
        </p>
        @if ($course->status === CourseStatus::Draft && filled($course->review_reason))
            <p role="status">{{ __('course_wizard.review_note', ['reason' => $course->review_reason]) }}</p>
        @endif
        <ul class="yg-wizard-checklist" aria-label="{{ __('course_wizard.finish_summary') }}">
            @foreach ($checks as $check)
                @php
                    $ok = $check['ok'];
                    $label = match (true) {
                        $ok => __('course_wizard.finish_ready'),
                        $check['level'] === CourseReadiness::ADVISORY => __('course_wizard.finish_optional'),
                        default => __('course_wizard.finish_missing'),
                    };
                @endphp
                <li class="{{ $ok ? 'is-done' : 'is-todo' }}">
                    <x-filament::icon :icon="$ok ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle'" />
                    <span>{{ __('course_wizard.check.'.$check['key']) }}</span>
                    <span class="fi-sr-only">{{ $label }}</span>
                    @unless ($ok)
                        <span>({{ $label }}@if ($check['step'] > 0), {{ $stepLabels[$check['step']] ?? '' }}@endif)</span>
                        @if ($check['key'] === 'curriculum')
                            <a href="{{ CourseResource::getUrl('curriculum', ['record' => $course]) }}">{{ __('curriculum.curriculum') }}</a>
                        @endif
                    @endunless
                </li>
            @endforeach
        </ul>
        <p>
            @if ($course->status === CourseStatus::Published)
                <a href="{{ PublicUrl::course($course, app()->getLocale()) }}" target="_blank" rel="noopener">{{ __('course_wizard.preview_link') }}</a>
            @else
                {{ __('course_wizard.preview_unavailable') }}
            @endif
        </p>
    @endif
</div>
