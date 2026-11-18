{{-- Props: course, wishlist, compact; optional price (Money) with market, and availability (CohortAvailability::forCourses entry) for the catalog. --}}
@props(['course', 'wishlist' => false, 'compact' => false, 'price' => null, 'market' => null, 'availability' => null])
@php($hasCover = $course->category?->image && $course->category->status === 'active')
<article @class(['min-w-0 rounded-xl border border-border bg-surface p-4', 'space-y-3' => ! $compact || ! $hasCover, 'grid items-start gap-4 sm:grid-cols-[2fr_3fr]' => $compact && $hasCover, 'shadow-sm' => $compact])>
    @if($hasCover)
        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($course->category->image) }}" alt=""
            loading="lazy" class="aspect-video w-full rounded-lg object-cover">
    @endif
    <div class="min-w-0 space-y-3">
    <h3 class="break-words text-lg font-bold">{{ $course->title }}</h3>
    @if($course->instructors->where('status', 'active')->isNotEmpty())
        <p class="break-words">{{ $course->instructors->where('status', 'active')->pluck('name')->implode(', ') }}</p>
    @endif
    @if(filled($course->short_description))
        <p class="break-words">{{ $course->short_description }}</p>
    @endif
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
    @if($price !== null && $market !== null)
        <p class="text-lg font-bold" data-course-price><x-ui.price :money="$price" :market="$market"/></p>
    @endif
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">{{ $slot }}</div>
        @if($wishlist)<x-engagement.wishlist-toggle :course="$course"/>@endif
    </div>
    </div>
</article>
