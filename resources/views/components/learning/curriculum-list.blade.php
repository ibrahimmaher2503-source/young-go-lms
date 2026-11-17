{{-- Props: course, sections (EntitledCurriculumQuery output), current item id, prefix (unique ids). Locked items show their reason. --}}
@props(['course', 'sections', 'current', 'prefix' => 'list'])
<div class="space-y-4" data-curriculum-list>
    @foreach($sections as $section)
        <section aria-labelledby="{{ $prefix }}-section-{{ $section['id'] }}">
            <h3 id="{{ $prefix }}-section-{{ $section['id'] }}" class="mb-2 font-bold break-words">{{ $section['title'] }}</h3>
            <ol class="space-y-1">
                @foreach($section['items'] as $entry)
                    <li>
                        @if($entry['state'] === 'locked')
                            <div class="rounded-lg border border-border bg-page p-2" data-item-state="locked">
                                <span class="font-medium break-words">{{ $entry['title'] }}</span>
                                <x-ui.badge>{{ __('learn.locked') }}</x-ui.badge>
                                @if($entry['reason'])<p class="mt-1 text-sm" data-lock-reason>{{ $entry['reason'] }}</p>@endif
                            </div>
                        @else
                            <a href="{{ route('learn.show', ['course' => $course->id, 'item' => $entry['id']]) }}"
                                class="flex min-h-11 flex-wrap items-center gap-2 rounded-lg border border-border p-2 {{ $entry['id'] === $current ? 'bg-accent font-semibold text-on-accent' : 'bg-surface' }}"
                                data-item-state="{{ $entry['state'] }}" @if($entry['id'] === $current) aria-current="page" @endif>
                                <span class="break-words">{{ $entry['title'] }}</span>
                                @if($entry['state'] === 'preview')<x-ui.badge tone="info">{{ __('learn.preview') }}</x-ui.badge>@endif
                            </a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endforeach
</div>
