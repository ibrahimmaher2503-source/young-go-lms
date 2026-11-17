{{-- Props: course, sections (EntitledCurriculumQuery output), current item id, completed ids, prefix (unique ids).
     Section cards with check / play / empty circles and minutes per lesson (C14); locked items show their reason. --}}
@props(['course', 'sections', 'current', 'completed' => [], 'prefix' => 'list', 'route' => 'learn.show'])
@php($number = 0)
<div class="space-y-3" data-curriculum-list>
    @foreach($sections as $sectionIndex => $section)
        <details open class="rounded-card border border-border bg-surface shadow-card" data-section>
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-3">
                <h3 id="{{ $prefix }}-section-{{ $section['id'] }}" class="font-bold break-words">{{ $sectionIndex + 1 }}. {{ $section['title'] }}</h3>
                <x-ui.icon name="chevron-down" class="size-5 text-muted" />
            </summary>
            <ol class="divide-y divide-border px-2 pb-2" aria-labelledby="{{ $prefix }}-section-{{ $section['id'] }}">
                @foreach($section['items'] as $entry)
                    @php($number++)
                    @php($isCurrent = $entry['id'] === $current)
                    @php($done = in_array($entry['id'], $completed, true))
                    <li>
                        @if($entry['state'] === 'locked')
                            <div class="flex items-start gap-3 rounded-xl px-2 py-3" data-item-state="locked">
                                <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full border border-border text-muted" aria-hidden="true"><x-ui.icon name="clock" class="size-4" /></span>
                                <div class="min-w-0">
                                    <p class="font-semibold break-words">{{ $number }}. {{ $entry['title'] }} <x-ui.badge>{{ __('learn.locked') }}</x-ui.badge></p>
                                    @if($entry['reason'])<p class="mt-1 text-sm text-muted" data-lock-reason>{{ $entry['reason'] }}</p>@endif
                                </div>
                            </div>
                        @else
                            <a href="{{ route($route, ['course' => $course->id, 'item' => $entry['id']]) }}"
                                class="flex items-center gap-3 rounded-xl px-2 py-3 {{ $isCurrent ? 'bg-nav-active-bg text-nav-active-text' : 'hover:bg-hover' }}"
                                data-item-state="{{ $entry['state'] }}" @if($isCurrent) aria-current="page" @endif>
                                <span class="grid size-8 shrink-0 place-items-center rounded-full {{ $isCurrent ? 'bg-primary text-on-primary' : ($done ? 'bg-success text-on-success' : 'border-2 border-border text-muted') }}" aria-hidden="true">
                                    @if($isCurrent)<x-ui.icon name="play" class="size-4" />@elseif($done)<x-ui.icon name="check" class="size-4" />@endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold break-words">{{ $number }}. {{ $entry['title'] }}</span>
                                    <span class="flex flex-wrap items-center gap-2 text-sm text-muted">
                                        @if($entry['minutes'] > 0)<span>{{ __('curriculum.minutes', ['count' => $entry['minutes']]) }}</span>@endif
                                        @if($entry['state'] === 'preview')<x-ui.badge tone="info">{{ __('learn.preview') }}</x-ui.badge>@endif
                                        @if($entry['draft'] ?? false)<x-ui.badge tone="warning" data-item-draft>{{ __('curriculum.status_draft') }}</x-ui.badge>@endif
                                        @if($done)<x-ui.badge tone="success" data-item-completed>{{ __('learn.completed') }}</x-ui.badge>@endif
                                    </span>
                                </span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </details>
    @endforeach
</div>
