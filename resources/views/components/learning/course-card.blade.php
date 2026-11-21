{{-- Props: course, wishlist, compact; priority (true on the first above-the-fold card: eager + fetchpriority=high, others lazy), optional approved instructor names, placeholder (true shows a token-colored cover block and prefers the course cover), price (Money) with market, and catalog availability. --}}
@props(['course', 'wishlist' => false, 'compact' => false, 'price' => null, 'market' => null, 'availability' => null, 'instructorNames' => null, 'placeholder' => false, 'lessons' => null, 'minutes' => null, 'priority' => false, 'catalog' => false, 'home' => false, 'rating' => null])
@php
    $categoryCover = $course->category?->status === 'active' ? $course->category->image : null;
    $cover = ($compact || $placeholder) && filled($course->cover_image) ? $course->cover_image : ($course->thumbnail_path ?? $course->cover_image ?? $categoryCover);
    $instructors = $instructorNames ?? $course->instructors->where('status', 'active')->map(static fn ($user): string => (string) $user->display_name)->filter()->implode(', ');
    $slug = strtolower((string) ($course->category?->slug_en ?? $course->slug_en ?? ''));
    $placeholderIcon = match (true) {
        str_contains($slug, 'draw') || str_contains($slug, 'art') || str_contains($slug, 'paint') => 'sparkle',
        str_contains($slug, 'voice') || str_contains($slug, 'audio') || str_contains($slug, 'sound') => 'play-circle',
        str_contains($slug, 'animat') || str_contains($slug, 'video') || str_contains($slug, 'film') => 'video',
        str_contains($slug, 'act') || str_contains($slug, 'theater') => 'users',
        str_contains($slug, 'design') || str_contains($slug, 'craft') => 'layers',
        default => 'book',
    };
    $placeholderTone = match (((int) $course->id) % 4) {
        0 => 'bg-gradient-to-br from-assistant to-accent text-primary',
        1 => 'bg-gradient-to-br from-nav-active-bg to-assistant text-nav-active-text',
        2 => 'bg-gradient-to-br from-surface-muted to-assistant text-primary',
        default => 'bg-assistant text-primary',
    };
@endphp
<article {{ $attributes->class([
    'min-w-0 rounded-xl border border-border bg-surface',
    'flex h-full flex-col gap-3 p-4' => ! $compact && ! $catalog,
    'flex h-full flex-col overflow-hidden shadow-card transition-shadow hover:shadow-md' => $catalog,
    'p-2.5 shadow-sm' => $compact,
    'grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3' => $compact && $cover,
]) }}>
    @if($cover)
        <div data-card-cover @class(['relative min-h-32 overflow-hidden rounded-lg' => $compact])>
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($cover) }}" alt="" width="640" height="360" @if($priority) loading="eager" fetchpriority="high" decoding="async" @else loading="lazy" decoding="async" @endif
                @class(['w-full object-cover', 'rounded-lg' => ! $catalog, 'absolute inset-0 h-full' => $compact, 'aspect-video' => ! $compact && ! $catalog, 'aspect-[2.45/1]' => $catalog])>
        </div>
    @elseif($placeholder)
        <div @class(['grid w-full place-items-center', $placeholderTone, 'aspect-video rounded-lg' => ! $catalog, 'aspect-[2.45/1]' => $catalog]) data-card-cover data-card-cover-placeholder><x-ui.icon :name="$placeholderIcon" class="size-10"/></div>
    @endif
    <div data-card-body @class(['min-w-0', 'flex flex-1 flex-col gap-2' => ! $compact, 'flex flex-col items-start gap-1 px-1 py-1.5' => $compact, 'px-4 pb-2 pt-3' => $catalog])>
    <h3 @class(['break-words font-bold', 'leading-snug line-clamp-2' => ! $compact, 'text-base' => ! $compact && ! $catalog, 'text-lg' => $compact || $catalog])>{{ $course->title }}</h3>
    <div @class(['home-course-meta' => $home, 'contents' => ! $home])>
    @if($instructors !== '')
        <p data-card-instructors class="flex items-start gap-1.5 break-words text-sm text-muted">@if($catalog)<x-ui.icon name="user" class="mt-0.5 size-4"/>@endif<bdi>{{ $instructors }}</bdi></p>
    @endif
    @if(! $compact && ! $catalog && $lessons !== null && $lessons > 0)
        <p class="flex items-center gap-1.5 text-xs text-muted" data-card-lessons><x-ui.icon name="layers" class="size-4"/>{{ trans_choice('curriculum.lessons_count_choice', $lessons, ['count' => $lessons]) }}</p>
    @endif
    @if(! $compact && ! $catalog && filled($course->short_description))
        <p class="break-words text-sm text-muted line-clamp-2">{{ $course->short_description }}</p>
    @endif
    @if($home ? $lessons > 0 : (! $compact || $availability !== null || ($price === null && $course->access_mode !== App\Models\Course::ACCESS_FREE)))
    <div data-card-details class="flex flex-wrap items-center gap-1.5 [&_span]:px-2.5 [&_span]:py-0.5 [&_span]:text-xs">
        @unless($home)<x-ui.badge :tone="$catalog ? ($course->format === 'live' ? 'success' : 'info') : 'neutral'">{{ __('courses.'.$course->format) }}</x-ui.badge>@endunless
        @if($catalog && $lessons > 0)
            <p class="inline-flex items-center gap-1 text-xs text-muted" data-card-lessons><x-ui.icon name="layers" class="size-4"/>{{ trans_choice('curriculum.lessons_count_choice', $lessons, ['count' => $lessons]) }}</p>
        @endif
        @if(! $catalog && $course->access_mode === App\Models\Course::ACCESS_FREE)
            <x-ui.badge>{{ __('courses.free') }}</x-ui.badge>
        @endif
        @if($availability !== null)
            @if($availability['seats_left'] !== null)<x-ui.badge tone="warning" data-availability="seats">{{ trans_choice('catalog.seats_left', $availability['seats_left'], ['count' => $availability['seats_left']]) }}</x-ui.badge>@endif
            @if($availability['starts_on'] !== null)<x-ui.badge tone="info" data-availability="soon">{{ __('catalog.starts_on', ['date' => $availability['starts_on']->locale(app()->getLocale())->isoFormat('LL')]) }}</x-ui.badge>@endif
            @if($availability['open'] && $availability['seats_left'] === null && $availability['starts_on'] === null && $course->access_mode !== App\Models\Course::ACCESS_SUBSCRIPTION)<x-ui.badge tone="success" data-availability="open">{{ __('catalog.avail_open') }}</x-ui.badge>@endif
            @if(! $availability['open'])<x-ui.badge data-availability="closed">{{ __('catalog.enrolment_closed') }}</x-ui.badge>@endif
        @endif
        @if(! $home && in_array($course->access_mode, [App\Models\Course::ACCESS_SUBSCRIPTION, App\Models\Course::ACCESS_BOTH], true))
            <x-ui.badge tone="info" data-availability="subscription">{{ __('catalog.included_in_subscription') }}</x-ui.badge>
        @endif
    </div>
    @endif
    @if($home && $minutes > 0)
        <p class="home-course-duration"><x-ui.icon name="clock"/>{{ trans_choice('curriculum.minutes_choice', $minutes) }}</p>
    @endif
    @if($home && filled($course->level) && trans()->has('courses.levels.'.$course->level))
        <p class="home-course-level"><x-ui.icon name="level"/>{{ __('courses.levels.'.$course->level) }}</p>
    @endif
    @if(($rating['count'] ?? 0) > 0)
        <p class="home-course-rating"><span class="sr-only">{{ __('public-site.rating', ['score' => $rating['average'], 'count' => $rating['count']]) }}</span><x-ui.icon name="star"/><bdi aria-hidden="true">{{ number_format($rating['average'], 1) }} ({{ $rating['count'] }})</bdi></p>
    @endif
    </div>
    <div data-card-footer @class(['mt-auto', 'pt-2' => ! $catalog, 'flex flex-wrap items-center justify-between gap-x-2 border-t border-border pt-1' => $catalog])>
        @if($price !== null && $market !== null)
            <p @class(['text-lg font-bold', 'text-primary' => $compact || $catalog]) data-course-price><x-ui.price :money="$price" :market="$market"/></p>
        @elseif(($compact || $catalog) && $course->access_mode === App\Models\Course::ACCESS_FREE)
            <p class="text-lg font-bold text-primary">{{ __('courses.free') }}</p>
        @elseif($home && in_array($course->access_mode, [App\Models\Course::ACCESS_SUBSCRIPTION, App\Models\Course::ACCESS_BOTH], true))
            <p class="text-sm font-semibold text-primary" data-availability="subscription">{{ __('catalog.included_in_subscription') }}</p>
        @endif
        <div data-card-actions @class(['flex flex-wrap items-center gap-2', 'justify-between' => ! $catalog, 'ms-auto' => $catalog, 'mt-2' => ! $catalog && ($price !== null || ($compact && $course->access_mode === App\Models\Course::ACCESS_FREE))])>
            <div class="min-w-0">{{ $slot }}</div>
            @if($wishlist)<x-engagement.wishlist-toggle :course="$course"/>@endif
        </div>
    </div>
    </div>
</article>
