@props(['course'])
<article class="min-w-0 space-y-3 rounded-xl border border-border bg-surface p-4">
    @if($course->category?->image && $course->category->status === 'active')
        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($course->category->image) }}" alt=""
            loading="lazy" class="aspect-video w-full rounded-lg object-cover">
    @endif
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
    {{ $slot }}
</article>
