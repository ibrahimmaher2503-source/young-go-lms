{{-- Props: course, wishlist, compact; optional approved instructor names, placeholder (true shows a token-colored cover block and prefers the course cover), price (Money) with market, and catalog availability. --}}
@props(['course', 'wishlist' => false, 'compact' => false, 'price' => null, 'market' => null, 'availability' => null, 'instructorNames' => null, 'placeholder' => false, 'lessons' => null])
@php
    $categoryCover = $course->category?->status === 'active' ? $course->category->image : null;
    $cover = ($compact || $placeholder) && filled($course->cover_image) ? $course->cover_image : $categoryCover;
    $instructors = $instructorNames ?? $course->instructors->where('status', 'active')->map(static fn ($user): string => (string) $user->display_name)->filter()->implode(', ');
@endphp
<article @class([
    'min-w-0 rounded-xl border border-border bg-surface',
    'space-y-3 p-4' => ! $compact,
    'p-2.5 shadow-sm' => $compact,
    'grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3' => $compact && $cover,
])>
    @if($cover)
        <div @class(['relative min-h-32 overflow-hidden rounded-lg' => $compact])>
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($cover) }}" alt="" loading="lazy"
                @class(['w-full rounded-lg object-cover', 'absolute inset-0 h-full' => $compact, 'aspect-video' => ! $compact])>
        </div>
    @elseif($placeholder)
        <div class="grid aspect-video w-full place-items-center rounded-lg bg-assistant text-primary" data-card-cover-placeholder><x-ui.icon name="book" class="size-10"/></div>
    @endif
    <div @class(['min-w-0', 'space-y-3' => ! $compact, 'flex flex-col items-start gap-1 px-1 py-1.5' => $compact])>
    <h3 class="break-words text-lg font-bold">{{ $course->title }}</h3>
    @if($instructors !== '')
        <p @class(['break-words', 'text-sm text-muted' => $compact])><bdi>{{ $instructors }}</bdi></p>
    @endif
    @if(! $compact && $lessons !== null && $lessons > 0)
        <p class="flex items-center gap-1.5 text-sm text-muted" data-card-lessons><x-ui.icon name="layers" class="size-4"/>{{ __('curriculum.lessons_count', ['count' => $lessons]) }}</p>
    @endif
    @if(! $compact && filled($course->short_description))
        <p class="break-words">{{ $course->short_description }}</p>
    @endif
    @if(! $compact || $availability !== null || ($price === null && $course->access_mode !== App\Models\Course::ACCESS_FREE))
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.badge>{{ __('courses.'.$course->format) }}</x-ui.badge>
        @if($course->access_mode === App\Models\Course::ACCESS_FREE)
            <x-ui.badge>{{ __('courses.free') }}</x-ui.badge>
        @endif
        @if($availability !== null)
            @if($availability['seats_left'] !== null)<x-ui.badge tone="warning" data-availability="seats">{{ trans_choice('catalog.seats_left', $availability['seats_left'], ['count' => $availability['seats_left']]) }}</x-ui.badge>@endif
            @if($availability['starts_on'] !== null)<x-ui.badge tone="info" data-availability="soon">{{ __('catalog.starts_on', ['date' => $availability['starts_on']->locale(app()->getLocale())->isoFormat('LL')]) }}</x-ui.badge>@endif
            @if($availability['open'] && $availability['seats_left'] === null && $availability['starts_on'] === null && $course->access_mode !== App\Models\Course::ACCESS_SUBSCRIPTION)<x-ui.badge tone="success" data-availability="open">{{ __('catalog.avail_open') }}</x-ui.badge>@endif
            @if(! $availability['open'])<x-ui.badge data-availability="closed">{{ __('catalog.enrolment_closed') }}</x-ui.badge>@endif
        @endif
        @if(in_array($course->access_mode, [App\Models\Course::ACCESS_SUBSCRIPTION, App\Models\Course::ACCESS_BOTH], true))
            <x-ui.badge tone="info" data-availability="subscription">{{ __('catalog.included_in_subscription') }}</x-ui.badge>
        @endif
    </div>
    @endif
    @if($price !== null && $market !== null)
        <p @class(['text-lg font-bold', 'text-primary' => $compact]) data-course-price><x-ui.price :money="$price" :market="$market"/></p>
    @elseif($compact && $course->access_mode === App\Models\Course::ACCESS_FREE)
        <p class="text-lg font-bold text-primary">{{ __('courses.free') }}</p>
    @endif
    <div @class(['flex flex-wrap items-center justify-between gap-2', 'mt-auto pt-2' => $compact])>
        <div class="min-w-0">{{ $slot }}</div>
        @if($wishlist)<x-engagement.wishlist-toggle :course="$course"/>@endif
    </div>
    </div>
</article>
