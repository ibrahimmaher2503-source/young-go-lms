@props(['course', 'wishlist' => false, 'compact' => false])
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
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">{{ $slot }}</div>
        @if($wishlist)<x-engagement.wishlist-toggle :course="$course"/>@endif
    </div>
    </div>
</article>
