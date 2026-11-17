<x-filament-panels::page>
    @include('filament.ai.styles')

    <div class="yg-rv" data-assistant-availability>
        <section class="yg-rv-card" aria-label="{{ __('ai_admin.availability.pick') }}">
            <p class="yg-rv-meta">{{ __('ai_admin.availability.help') }}</p>
            <div class="yg-ai-field">
                <label for="av-course">{{ __('ai_admin.availability.course') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="av-course" wire:model.live="courseId">
                        <option value="">{{ __('ai_admin.availability.course_placeholder') }}</option>
                        @foreach($courses as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </section>

        @if($courses === [])
            <section class="yg-rv-card" data-availability-empty><p>{{ __('ai_admin.availability.no_courses') }}</p></section>
        @elseif($course === null)
            <section class="yg-rv-card"><p class="yg-rv-meta">{{ __('ai_admin.availability.choose') }}</p></section>
        @else
            <section class="yg-rv-card" aria-label="{{ __('ai_admin.availability.course_switch') }}" data-section="course">
                <div class="yg-ai-row">
                    <div>
                        <h3 class="yg-rv-title">{{ $course->title }}</h3>
                        <p class="yg-rv-meta">{{ __('ai_admin.availability.course_help') }}</p>
                    </div>
                    <button type="button" class="yg-ai-switch" role="switch" aria-pressed="{{ $course->ai_enabled ? 'true' : 'false' }}" wire:click="toggleCourse" wire:loading.attr="disabled" data-course-switch="{{ $course->ai_enabled ? 'on' : 'off' }}">
                        {{ $course->ai_enabled ? __('ai_admin.availability.on') : __('ai_admin.availability.off') }}
                    </button>
                </div>
            </section>

            <section class="yg-rv-card" aria-label="{{ __('ai_admin.availability.lessons') }}" data-section="lessons">
                <h3 class="yg-rv-title">{{ __('ai_admin.availability.lessons') }}</h3>
                @if($lessons === [])
                    <p class="yg-rv-meta">{{ __('ai_admin.availability.no_lessons') }}</p>
                @else
                    <div class="yg-ai-scroll">
                        <table class="yg-ai-table">
                            <thead><tr>
                                <th scope="col">{{ __('ai_admin.availability.lesson') }}</th>
                                <th scope="col">{{ __('ai_admin.availability.pdf') }}</th>
                                <th scope="col">{{ __('ai_admin.availability.assistant') }}</th>
                            </tr></thead>
                            <tbody>
                            @foreach($lessons as $row)
                                <tr data-lesson-row="{{ $row['item_id'] }}">
                                    <td>{{ $row['title'] }} <span class="yg-rv-meta">· {{ __('curriculum.types.'.$row['type']) }}</span></td>
                                    <td><span class="yg-rv-badge" data-readiness="{{ $row['readiness']->value }}"><span aria-hidden="true">{{ $row['readiness']->icon() }}</span>&nbsp;{{ $row['readiness']->label() }}</span></td>
                                    <td>
                                        <button type="button" class="yg-ai-switch" role="switch" aria-pressed="{{ $row['enabled'] ? 'true' : 'false' }}" aria-label="{{ $row['title'] }}" wire:click="toggleLesson({{ $row['item_id'] }})" wire:loading.attr="disabled" data-lesson-switch="{{ $row['enabled'] ? 'on' : 'off' }}">
                                            {{ $row['enabled'] ? __('ai_admin.availability.on') : __('ai_admin.availability.off') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
