@php
    $locale = app()->getLocale();
    $text = static fn (array $row, string $field): string => (string) (($row[$field][$locale] ?? null) ?: ($row[$field]['en'] ?? ($row[$field]['ar'] ?? '')));
    $outcomes = collect($course->outcomes ?? [])->map(fn ($row) => $text((array) $row, 'text'))->filter()->values();
    $requirements = collect($course->requirements ?? [])->map(fn ($row) => $text((array) $row, 'text'))->filter()->values();
    $faqs = collect($course->faqs ?? [])->map(fn ($row) => ['q' => $text((array) $row, 'question'), 'a' => $text((array) $row, 'answer')])->filter(fn ($row) => $row['q'] !== '')->values();
    $instructorRows = $course->instructors->map(function ($user) use ($profiles, $locale) {
        $profile = $profiles->get($user->getKey());

        return [
            'name' => (string) ($profile?->publicName($locale) ?: $user->display_name),
            'headline' => $profile?->publicHeadline($locale, fallback: true) ?? '',
            'avatar' => $profile?->avatarUrl(),
            'url' => $profile ? App\Domain\Seo\PublicUrl::instructor($profile, $locale) : null,
        ];
    })->filter(fn ($row) => $row['name'] !== '')->values();
    $instructors = $instructorRows->pluck('name');
    $cover = $course->coverUrl();
    $customRows = \App\Domain\Catalog\CourseCustomValues::publicRows($course);
    $previewRoute = request()->routeIs('public.catalog.show') ? 'public.catalog.preview' : 'catalog.preview';
    $previewParams = $previewRoute === 'public.catalog.preview' ? ['publicLocale' => $locale, 'course' => $key] : ['course' => $key];
    $previewUrl = $curriculum['preview_item'] ? route($previewRoute, $previewParams + ['item' => $curriculum['preview_item']]) : null;
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
    $accessLabel = __(match ($course->access_mode) {
        App\Models\Course::ACCESS_FREE => 'courses.access_free',
        App\Models\Course::ACCESS_PAID => 'courses.access_paid',
        App\Models\Course::ACCESS_SUBSCRIPTION => 'courses.access_subscription',
        App\Models\Course::ACCESS_BOTH => 'courses.access_both',
        default => 'courses.unconfigured',
    });
    $sectionLinks = array_filter([
        'overview' => __('course_page.overview'),
        'curriculum' => __('course_page.curriculum'),
        'instructor' => $instructors->isNotEmpty() ? __('course_page.instructor') : null,
        'faq' => $faqs->isNotEmpty() ? __('course_page.faq') : null,
    ]);
    $coursePlans = $coursePlans ?? collect();
    $courseBundles = $courseBundles ?? collect();
    $cta = [
        'course' => $course,
        'entitled' => $entitled,
        'signedIn' => $signedIn,
        'purchasable' => $price !== null,
        'subscribable' => $coursePlans->isNotEmpty(),
        'freeEnrollable' => $freeEnrollable ?? ($course->access_mode === App\Models\Course::ACCESS_FREE),
        'enrollLearners' => $enrollLearners ?? collect(),
        'enrollOwned' => $enrollOwned ?? [],
        'seats' => $seats,
    ];
    $linkClass = 'inline-flex min-h-11 items-center rounded-md font-semibold text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp
<x-layouts.base :title="$seo['title']" :locale-alternates="$seo['alternates']" variant="public" full indexable>
    <x-slot:head>
        <x-seo.head :seo="$seo"/>
    </x-slot:head>

    <div class="public-band public-band-header mb-4">
        <x-ui.breadcrumb :items="[['label' => __('catalog.title'), 'url' => \App\Domain\Seo\PublicUrl::catalog($locale)], ['label' => $course->title]]"/>
    </div>

    <div class="grid gap-x-4 gap-y-7 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]" data-course-page>
        <div class="public-page-stack min-w-0">
            <section class="grid items-center gap-5 rounded-card border border-border bg-surface p-4 shadow-card md:grid-cols-2" aria-labelledby="course-title" data-section="hero">
                <div class="min-w-0 space-y-4">
                    <h1 id="course-title" class="wrap-anywhere text-3xl leading-tight font-extrabold text-nav-active-text xl:text-4xl" data-course-title>{{ $course->title }}</h1>
                    @if(filled($course->short_description))<p class="break-words text-muted">{{ $course->short_description }}</p>@endif
                    <ul class="flex flex-wrap items-center gap-2" aria-label="{{ __('course_page.facts') }}">
                        @if($instructors->isNotEmpty())<li class="min-w-0"><x-ui.badge tone="info" data-fact="instructor"><bdi class="wrap-anywhere">{{ __('course_page.instructor_name', ['name' => $instructors->implode(', ')]) }}</bdi></x-ui.badge></li>@endif
                        @if($levelLabel)<li><x-ui.badge data-fact="level">{{ $levelLabel }}</x-ui.badge></li>@endif
                        @if($languageLabel)<li><x-ui.badge data-fact="language">{{ $languageLabel }}</x-ui.badge></li>@endif
                        <li><x-ui.badge data-fact="format">{{ __('courses.'.$course->format) }}</x-ui.badge></li>
                        @if($ages)<li><x-ui.badge data-fact="age">{{ $ages }}</x-ui.badge></li>@endif
                    </ul>
                </div>
                <div class="relative self-start overflow-hidden rounded-lg bg-assistant md:order-first" data-hero-media>
                        @if($cover)<img src="{{ $cover }}" alt="" width="1280" height="720" fetchpriority="high" decoding="async" class="aspect-video w-full object-cover lg:aspect-[2.14/1]">@else<div class="grid aspect-video w-full place-items-center text-primary lg:aspect-[2.14/1]" data-cover-placeholder><x-ui.icon name="book" class="size-14"/></div>@endif
                        @if($previewUrl)
                            <a href="{{ $previewUrl }}" class="group absolute inset-0 grid place-items-center rounded-lg focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary" aria-label="{{ __('course_page.watch_preview') }}" data-preview-link>
                                <span class="grid size-14 place-items-center rounded-full border-2 border-surface bg-surface text-primary shadow-card group-hover:bg-primary group-hover:text-on-primary"><x-ui.icon name="play" class="size-6"/></span>
                            </a>
                        @endif
                    </div>
            </section>

            <nav class="rounded-card border border-border bg-surface p-1 shadow-card" aria-label="{{ __('course_page.sections') }}" data-course-sections>
                <ul class="flex flex-wrap gap-1">
                    @foreach($sectionLinks as $target => $label)
                        <li class="min-w-0 flex-1 basis-32">
                            <a href="#{{ $target }}" class="flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-center text-sm font-semibold text-nav-active-text hover:bg-nav-active-bg focus-visible:outline-2 focus-visible:outline-primary">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <section id="overview" class="scroll-mt-4 space-y-4" tabindex="-1" aria-label="{{ __('course_page.overview') }}" data-section="overview">
                @if(filled($course->description))
                    <x-ui.card :title="__('course_page.about')"><div class="break-words whitespace-pre-line" dir="auto">{!! \App\Domain\Catalog\CourseDescription::clean($course->description) !!}</div></x-ui.card>
                @elseif($outcomes->isEmpty() && $requirements->isEmpty() && $customRows === [] && filled($course->short_description))
                    <x-ui.card :title="__('course_page.about')"><p class="break-words whitespace-pre-line" dir="auto">{{ $course->short_description }}</p></x-ui.card>
                @endif
                @if($outcomes->isNotEmpty())
                    <x-ui.card :title="__('course_page.outcomes')" data-section="outcomes">
                        <ul class="grid gap-3 sm:grid-cols-2">
                            @foreach($outcomes as $outcome)
                                <li class="flex items-start gap-3 rounded-lg bg-assistant p-3">
                                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-nav-active-bg text-nav-active-text" aria-hidden="true"><x-ui.icon name="check" class="size-4"/></span>
                                    <span class="min-w-0 break-words">{{ $outcome }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif

                @if($customRows !== [])
                    <x-ui.card :title="__('course_wizard.custom_extra')" data-section="custom-fields">
                        <dl class="grid gap-3 sm:grid-cols-2">@foreach($customRows as $row)<div><dt class="text-muted">{{ $row['label'] }}</dt><dd class="break-words font-semibold">{{ $row['value'] }}</dd></div>@endforeach</dl>
                    </x-ui.card>
                @endif
            </section>

            <x-ui.card id="curriculum" class="scroll-mt-4" tabindex="-1" aria-labelledby="course-curriculum-title" data-section="curriculum">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="course-curriculum-title" class="text-lg font-bold">{{ __('course_page.curriculum') }}</h2>
                    @if($curriculum['sections'] !== [])
                        <div class="flex flex-wrap items-center gap-x-4 text-sm text-muted">
                            <span class="inline-flex items-center gap-1.5"><x-ui.icon name="layers" class="size-4"/>{{ trans_choice('curriculum.lessons_count_choice', $curriculum['lessons'], ['count' => $curriculum['lessons']]) }}</span>
                            @if($curriculum['minutes'] > 0)<span class="inline-flex items-center gap-1.5"><x-ui.icon name="clock" class="size-4"/>{{ trans_choice('curriculum.minutes_choice', $curriculum['minutes'], ['count' => $curriculum['minutes']]) }}</span>@endif
                        </div>
                    @endif
                </div>
                @if($curriculum['sections'] === [])
                    <p class="text-muted" data-curriculum-empty>{{ __('course_page.curriculum_empty') }}</p>
                @else
                    <div class="space-y-2">
                        @foreach($curriculum['sections'] as $index => $section)
                            <x-ui.accordion :label="($index + 1).'. '.$section['title']" :open="$index === 0" class="bg-page/50" data-curriculum-section>
                                <ol class="divide-y divide-border">
                                    @foreach($section['items'] as $entry)
                                        <li class="flex flex-wrap items-center justify-between gap-2 py-2" data-lesson-row data-state="{{ $entry['preview'] ? 'preview' : 'locked' }}">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <x-ui.icon :name="$entry['preview'] ? 'play-circle' : 'clock'" class="size-5 shrink-0 text-muted"/>
                                                <span class="break-words">{{ $entry['title'] }}</span>
                                            </span>
                                            <span class="flex flex-wrap items-center gap-2 text-sm text-muted">
                                                @if($entry['minutes'] > 0)<span>{{ trans_choice('curriculum.minutes_choice', $entry['minutes'], ['count' => $entry['minutes']]) }}</span>@endif
                                                @if($entry['preview'])
                                                    <a href="{{ route($previewRoute, $previewParams + ['item' => $entry['id']]) }}" class="{{ $linkClass }}" data-preview-link>{{ __('course_page.free_preview') }}</a>
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

            @if($requirements->isNotEmpty() || $faqs->isNotEmpty())
                <div @class(['grid gap-4', 'md:grid-cols-2' => $requirements->isNotEmpty() && $faqs->isNotEmpty()])>
                    @if($requirements->isNotEmpty())
                        <x-ui.card id="requirements" :title="__('course_page.requirements')" data-section="requirements">
                            <ul class="list-disc space-y-2 ps-5 marker:text-primary">@foreach($requirements as $requirement)<li class="wrap-anywhere">{{ $requirement }}</li>@endforeach</ul>
                        </x-ui.card>
                    @endif
                    @if($faqs->isNotEmpty())
                        <x-ui.card id="faq" :title="__('course_page.faq')" class="scroll-mt-4" tabindex="-1" data-section="faq">
                            <div class="space-y-2">@foreach($faqs as $faq)<x-ui.accordion :label="$faq['q']" class="bg-page/50"><p class="wrap-anywhere text-muted">{{ $faq['a'] }}</p></x-ui.accordion>@endforeach</div>
                        </x-ui.card>
                    @endif
                </div>
            @endif

            @if($instructors->isNotEmpty())
                <x-ui.card id="instructor" :title="__('course_page.instructor')" class="scroll-mt-4" tabindex="-1" data-section="instructor">
                    <ul class="space-y-2">
                        @foreach($instructorRows as $row)
                            <li class="flex items-center gap-3">
                                <x-ui.avatar :name="$row['name']" :src="$row['avatar']" aria-hidden="true"/>
                                <div class="min-w-0">
                                    @if($row['url'])<a href="{{ $row['url'] }}" class="{{ $linkClass }} wrap-anywhere" data-instructor-link>{{ $row['name'] }}</a>@else<span class="wrap-anywhere font-semibold">{{ $row['name'] }}</span>@endif
                                    @if($row['headline'] !== '')<p class="wrap-anywhere text-sm text-muted" dir="auto">{{ $row['headline'] }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            {{-- Reviews slot (T-P9-02): published reviews and the entitled learner's form; stays hidden until there is something to show. --}}
            <x-engagement.reviews :course="$course"/>
        </div>

        <aside class="lg:sticky lg:top-4 lg:self-start" aria-label="{{ __('course_page.access') }}" data-access-card>
            <x-ui.card class="space-y-4 shadow-card">
                @if($cover)
                    <img src="{{ $cover }}" alt="" width="960" height="540" loading="lazy" decoding="async" class="hidden aspect-video w-full rounded-lg object-cover lg:block">
                @endif
                <div class="text-center">
                    @if($price !== null)
                        <p class="text-3xl font-extrabold"><x-ui.price :money="$price" :market="$market" data-course-price /></p>
                    @endif
                    <p @class(['text-sm text-muted' => $price !== null, 'text-xl font-bold' => $price === null]) data-access-mode>{{ $accessLabel }}</p>
                </div>
                @if($seats['cohorts'] && ($seats['full'] || $seats['seats_left'] !== null))
                    <div class="text-center" data-seat-state="{{ $seats['full'] ? 'full' : 'seats' }}">
                        @if($seats['full'])
                            <x-ui.badge tone="warning">{{ __('cohorts.full_label') }}</x-ui.badge>
                            @if($signedIn && ! $entitled && ! $seats["waitlisted"])<p class="mt-2 text-sm text-muted">{{ __('cohorts.waitlist_hint') }}</p>@endif
                        @else
                            <x-ui.badge tone="info">{{ __('cohorts.seats_left', ['count' => $seats['seats_left']]) }}</x-ui.badge>
                        @endif
                    </div>
                @endif
                @if(session('waitlist_notice'))<x-ui.alert tone="success" role="status">{{ session('waitlist_notice') }}</x-ui.alert>@endif
                @if(session('enrollment_notice'))<x-ui.alert tone="success" role="status">{{ session('enrollment_notice') }}</x-ui.alert>@endif
                @if($errors->has('cohort'))<x-ui.alert tone="danger" role="alert">{{ $errors->first('cohort') }}</x-ui.alert>@endif
                @if($errors->has('course'))<x-ui.alert tone="danger" role="alert">{{ $errors->first('course') }}</x-ui.alert>@endif
                @if($errors->has('child_id'))<x-ui.alert tone="danger" role="alert">{{ $errors->first('child_id') }}</x-ui.alert>@endif
                @include('catalog._cta', $cta + ['showLearnerPicker' => true])
                @if(! $entitled && $coursePlans->isNotEmpty())
                    <div class="space-y-2 rounded-lg border border-border bg-assistant p-3 text-sm" data-course-plans>
                        <p class="font-bold">{{ __('subscriptions.course_plans_title') }}</p>
                        <ul class="space-y-1.5">
                            @foreach($coursePlans as $planOffer)
                                @php $planPrice = $planOffer->priceFor($market); @endphp
                                <li class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-semibold"><bdi>{{ $planOffer->name }}</bdi></span>
                                    @if($planPrice)
                                        <span class="text-muted"><x-ui.price :money="\App\Domain\Shared\Money\Money::ofMinor($planPrice->amount_minor, $planPrice->currency)" :market="$market" /> / {{ __('subscriptions.term_'.$planOffer->term->value) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ \App\Domain\Seo\PublicUrl::plans($locale) }}" class="{{ $linkClass }} text-sm">{{ __('subscriptions.subscribe_to_access') }}</a>
                    </div>
                @endif
                @if(! $entitled && $courseBundles->isNotEmpty())
                    <div class="space-y-2 rounded-lg border border-border bg-page p-3 text-sm" data-course-bundles>
                        <p class="font-bold">{{ __('subscriptions.course_bundles_title') }}</p>
                        <ul class="space-y-2">
                            @foreach($courseBundles as $bundleOffer)
                                @php $bundlePrice = $bundleOffer->priceFor($market); @endphp
                                <li class="flex flex-wrap items-center justify-between gap-2">
                                    <a href="{{ \App\Domain\Seo\PublicUrl::bundle($bundleOffer, $locale) }}" class="{{ $linkClass }}"><bdi>{{ $bundleOffer->name }}</bdi></a>
                                    @if($bundlePrice)
                                        <span class="font-semibold"><x-ui.price :money="\App\Domain\Shared\Money\Money::ofMinor($bundlePrice->amount_minor, $bundlePrice->currency)" :market="$market" /></span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if($entitled && ($cta['freeEnrollable']) && ! ($isParentContext ?? false))
                    @php
                        $unenrolledChildren = $cta['enrollLearners']->filter(fn ($u) => $u->getKey() !== auth()->id() && ! ($cta['enrollOwned'][$u->getKey()] ?? false))->values();
                    @endphp
                    @if($unenrolledChildren->isNotEmpty())
                        <form method="post" action="{{ route('catalog.enroll', ['course' => $course->id]) }}" class="space-y-3 border-t border-border pt-3" data-enroll-child-form>
                            @csrf
                            <x-ui.form-field for="enroll-child-id" :label="__('course_page.enroll_for')">
                                <x-ui.select id="enroll-child-id" name="child_id">
                                    @foreach($unenrolledChildren as $child)
                                        <option value="{{ $child->getKey() }}">{{ $child->display_name ?: $child->name }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.form-field>
                            <x-ui.button type="submit" variant="secondary" class="w-full" data-cta="enroll-child">{{ __('course_page.enroll_child') }}</x-ui.button>
                        </form>
                    @endif
                @endif
                <x-engagement.wishlist-toggle :course="$course" class="flex justify-center"/>
                @if($previewUrl)
                    <a href="{{ $previewUrl }}" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-border px-5 py-3 font-semibold text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" data-preview-link><x-ui.icon name="play-circle"/> {{ __('course_page.watch_preview') }}</a>
                @endif
                <ul class="divide-y divide-border">
                    <li class="flex items-center gap-3 py-2"><x-ui.icon name="layers" class="size-5 text-muted"/>{{ trans_choice('curriculum.lessons_count_choice', $curriculum['lessons'], ['count' => $curriculum['lessons']]) }}</li>
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
            @if($price !== null)
                <p class="shrink-0 font-bold"><x-ui.price :money="$price" :market="$market" data-course-price /></p>
            @else
                <p class="max-w-1/2 font-bold">{{ $accessLabel }}</p>
            @endif
            <div class="min-w-0 flex-1">@include('catalog._cta', $cta + ['showLearnerPicker' => false])</div>
        </div>
    </div>
</x-layouts.base>
