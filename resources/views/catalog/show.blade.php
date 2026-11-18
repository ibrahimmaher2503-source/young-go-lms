@php
    $locale = app()->getLocale();
    $text = static fn (array $row, string $field): string => (string) (($row[$field][$locale] ?? null) ?: ($row[$field]['en'] ?? ($row[$field]['ar'] ?? '')));
    $outcomes = collect($course->outcomes ?? [])->map(fn ($row) => $text((array) $row, 'text'))->filter()->values();
    $requirements = collect($course->requirements ?? [])->map(fn ($row) => $text((array) $row, 'text'))->filter()->values();
    $faqs = collect($course->faqs ?? [])->map(fn ($row) => ['q' => $text((array) $row, 'question'), 'a' => $text((array) $row, 'answer')])->filter(fn ($row) => $row['q'] !== '')->values();
    $instructorRows = $course->instructors->map(function ($user) use ($profiles, $locale) {
        $profile = $profiles->get($user->getKey());

        return ['name' => (string) ($profile?->publicName($locale) ?: $user->name), 'url' => $profile ? route('instructors.show', ['instructor' => App\Domain\Instructors\Queries\PublicInstructors::keyFor($profile, $locale)]) : null];
    })->filter(fn ($row) => $row['name'] !== '')->values();
    $instructors = $instructorRows->pluck('name');
    $cover = $course->category?->image && $course->category->status === 'active' ? Illuminate\Support\Facades\Storage::disk('public')->url($course->category->image) : null;
    $previewUrl = $curriculum['preview_item'] ? route('catalog.preview', ['course' => $key, 'item' => $curriculum['preview_item']]) : null;
    $levelLabel = filled($course->level) ? (trans()->has('catalog.'.$course->level) ? __('catalog.'.$course->level) : $course->level) : null;
    $languageLabel = filled($course->content_language) ? (trans()->has('catalog.'.$course->content_language) ? __('catalog.'.$course->content_language) : $course->content_language) : null;
    $ages = null;
    if ($course->min_age !== null && $course->max_age !== null) {
        $ages = __('course_page.ages_range', ['min' => $course->min_age, 'max' => $course->max_age]);
    } elseif ($course->min_age !== null) {
        $ages = __('course_page.ages_from', ['min' => $course->min_age]);
    } elseif ($course->max_age !== null) {
        $ages = __('course_page.ages_to', ['max' => $course->max_age]);
    }
    $accessLabel = $course->access_mode === App\Models\Course::ACCESS_FREE ? __('courses.free') : __('course_page.paid');
    $cta = ['course' => $course, 'entitled' => $entitled, 'signedIn' => $signedIn, 'purchasable' => $purchasable ?? false];
    $linkClass = 'rounded-md font-semibold text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp
<x-layouts.base :title="$seo['title']" variant="public" full indexable>
    <x-slot:head>
        <x-seo.head :seo="$seo"/>
    </x-slot:head>

    <div class="mb-4">
        <x-ui.breadcrumb :items="[['label' => __('catalog.title'), 'url' => route('catalog.index')], ['label' => $course->title]]"/>
    </div>

    <div class="grid gap-4 pb-24 lg:grid-cols-[minmax(0,1fr)_20rem] lg:pb-0 xl:grid-cols-[minmax(0,1fr)_22rem]" data-course-page>
        <div class="min-w-0 space-y-4">
            <section class="grid gap-5 rounded-card border border-border bg-surface p-4 shadow-card sm:p-6 {{ $cover || $previewUrl ? 'md:grid-cols-2' : '' }}" aria-labelledby="course-title" data-section="hero">
                <div class="min-w-0 space-y-4">
                    <h1 id="course-title" class="break-words text-3xl font-extrabold" data-course-title>{{ $course->title }}</h1>
                    @if(filled($course->short_description))<p class="break-words text-muted">{{ $course->short_description }}</p>@endif
                    <ul class="flex flex-wrap items-center gap-2" aria-label="{{ __('course_page.facts') }}">
                        @if($instructors->isNotEmpty())<li><x-ui.badge tone="info" data-fact="instructor">{{ __('course_page.instructor_name', ['name' => $instructors->implode(', ')]) }}</x-ui.badge></li>@endif
                        @if($levelLabel)<li><x-ui.badge data-fact="level">{{ $levelLabel }}</x-ui.badge></li>@endif
                        @if($languageLabel)<li><x-ui.badge data-fact="language">{{ $languageLabel }}</x-ui.badge></li>@endif
                        <li><x-ui.badge data-fact="format">{{ __('courses.'.$course->format) }}</x-ui.badge></li>
                        @if($ages)<li><x-ui.badge data-fact="age">{{ $ages }}</x-ui.badge></li>@endif
                    </ul>
                </div>
                @if($cover || $previewUrl)
                    <div class="relative self-start overflow-hidden rounded-lg bg-assistant md:order-first" data-hero-media>
                        @if($cover)<img src="{{ $cover }}" alt="" class="aspect-video w-full object-cover">@else<div class="aspect-video w-full"></div>@endif
                        @if($previewUrl)
                            <a href="{{ $previewUrl }}" class="absolute inset-0 grid place-items-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" aria-label="{{ __('course_page.watch_preview') }}" data-preview-link>
                                <span class="grid size-14 place-items-center rounded-full bg-surface text-primary shadow-card"><x-ui.icon name="play" class="size-6"/></span>
                            </a>
                        @endif
                    </div>
                @endif
            </section>

            <nav aria-label="{{ __('course_page.sections') }}" class="flex flex-wrap gap-2 rounded-card border border-border bg-surface p-2 md:justify-around">
                <x-ui.link href="#overview" class="px-3">{{ __('course_page.overview') }}</x-ui.link>
                <x-ui.link href="#curriculum" class="px-3">{{ __('course_page.curriculum') }}</x-ui.link>
                @if($instructors->isNotEmpty())<x-ui.link href="#instructor" class="px-3">{{ __('course_page.instructor') }}</x-ui.link>@endif
                @if($faqs->isNotEmpty())<x-ui.link href="#faq" class="px-3">{{ __('course_page.faq') }}</x-ui.link>@endif
            </nav>

            <section id="overview" class="space-y-4" aria-label="{{ __('course_page.overview') }}" data-section="overview">
                @if(filled($course->description))
                    <x-ui.card :title="__('course_page.about')"><div class="break-words whitespace-pre-line" dir="auto">{!! \App\Domain\Catalog\CourseDescription::clean($course->description) !!}</div></x-ui.card>
                @endif
                @if($outcomes->isNotEmpty())
                    <x-ui.card :title="__('course_page.outcomes')" data-section="outcomes">
                        <ul class="grid gap-3 sm:grid-cols-2">
                            @foreach($outcomes as $outcome)
                                <li class="flex items-start gap-3 rounded-lg bg-page p-3">
                                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-success text-on-success" aria-hidden="true"><x-ui.icon name="check" class="size-4"/></span>
                                    <span class="min-w-0 break-words">{{ $outcome }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            </section>

            <x-ui.card id="curriculum" :title="__('course_page.curriculum')" data-section="curriculum">
                @if($curriculum['sections'] === [])
                    <p class="text-muted" data-curriculum-empty>{{ __('course_page.curriculum_empty') }}</p>
                @else
                    <p class="mb-3 flex flex-wrap gap-x-4 text-sm text-muted">
                        <span>{{ __('curriculum.lessons_count', ['count' => $curriculum['lessons']]) }}</span>
                        @if($curriculum['minutes'] > 0)<span>{{ __('curriculum.minutes', ['count' => $curriculum['minutes']]) }}</span>@endif
                    </p>
                    <div class="space-y-2">
                        @foreach($curriculum['sections'] as $index => $section)
                            <x-ui.accordion :label="($index + 1).'. '.$section['title']" :open="$index === 0" data-curriculum-section>
                                <ol class="divide-y divide-border">
                                    @foreach($section['items'] as $entry)
                                        <li class="flex flex-wrap items-center justify-between gap-2 py-2" data-lesson-row data-state="{{ $entry['preview'] ? 'preview' : 'locked' }}">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <x-ui.icon :name="$entry['preview'] ? 'play-circle' : 'clock'" class="size-5 shrink-0 text-muted"/>
                                                <span class="break-words">{{ $entry['title'] }}</span>
                                            </span>
                                            <span class="flex flex-wrap items-center gap-2 text-sm text-muted">
                                                @if($entry['minutes'] > 0)<span>{{ __('curriculum.minutes', ['count' => $entry['minutes']]) }}</span>@endif
                                                @if($entry['preview'])
                                                    <a href="{{ route('catalog.preview', ['course' => $key, 'item' => $entry['id']]) }}" class="{{ $linkClass }}" data-preview-link>{{ __('course_page.free_preview') }}</a>
                                                @else
                                                    <x-ui.badge data-lock>{{ __('learn.locked') }}</x-ui.badge>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                            </x-ui.accordion>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            @if($instructors->isNotEmpty())
                <x-ui.card id="instructor" :title="__('course_page.instructor')" data-section="instructor">
                    <ul class="space-y-2">
                        @foreach($instructorRows as $row)<li class="flex items-center gap-2"><x-ui.icon name="user" class="size-5 text-muted"/>@if($row['url'])<a href="{{ $row['url'] }}" class="{{ $linkClass }} break-words" data-instructor-link>{{ $row['name'] }}</a>@else<span class="break-words">{{ $row['name'] }}</span>@endif</li>@endforeach
                    </ul>
                </x-ui.card>
            @endif

            <div class="grid gap-6 md:grid-cols-2">
                @if($requirements->isNotEmpty())
                    <x-ui.card :title="__('course_page.requirements')" data-section="requirements">
                        <ul class="list-disc space-y-2 ps-5">@foreach($requirements as $requirement)<li class="break-words">{{ $requirement }}</li>@endforeach</ul>
                    </x-ui.card>
                @endif
                @if($faqs->isNotEmpty())
                    <x-ui.card id="faq" :title="__('course_page.faq')" data-section="faq">
                        <div class="space-y-2">@foreach($faqs as $faq)<x-ui.accordion :label="$faq['q']"><p class="break-words">{{ $faq['a'] }}</p></x-ui.accordion>@endforeach</div>
                    </x-ui.card>
                @endif
            </div>

            {{-- Reviews slot (T-P9-02): published reviews and the entitled learner's form; stays hidden until there is something to show. --}}
            <x-engagement.reviews :course="$course"/>
        </div>

        <aside class="lg:sticky lg:top-4 lg:self-start" aria-label="{{ __('course_page.access') }}" data-access-card>
            <x-ui.card class="space-y-4">
                @if($cover)<img src="{{ $cover }}" alt="" class="aspect-video w-full rounded-lg object-cover">@endif
                <p class="text-xl font-bold" data-access-mode>{{ $accessLabel }}</p>
                @include('catalog._cta', $cta)
                <x-engagement.wishlist-toggle :course="$course" class="flex justify-center"/>
                @if($previewUrl)
                    <a href="{{ $previewUrl }}" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-border px-5 py-3 font-semibold text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" data-preview-link><x-ui.icon name="play-circle"/> {{ __('course_page.watch_preview') }}</a>
                @endif
                <ul class="divide-y divide-border">
                    <li class="flex items-center gap-3 py-2"><x-ui.icon name="layers" class="size-5 text-muted"/>{{ __('curriculum.lessons_count', ['count' => $curriculum['lessons']]) }}</li>
                    <li class="flex items-center gap-3 py-2"><x-ui.icon name="video" class="size-5 text-muted"/>{{ __('courses.'.$course->format) }}</li>
                    @if($ages)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2" data-access-age>
                            <x-ui.icon name="users" class="size-5 shrink-0 text-muted"/>
                            <span>{{ __('course_page.age_suitability') }}</span>
                            <span class="ms-auto text-sm text-muted">{{ $ages }}</span>
                        </li>
                    @endif
                </ul>
            </x-ui.card>
        </aside>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-surface p-3 shadow-card lg:hidden" data-mobile-cta>
        <div class="mx-auto flex max-w-md items-center gap-3">
            <p class="shrink-0 font-bold">{{ $accessLabel }}</p>
            <div class="min-w-0 flex-1">@include('catalog._cta', $cta)</div>
        </div>
    </div>
</x-layouts.base>
