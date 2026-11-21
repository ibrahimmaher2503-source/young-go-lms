{{-- Props: course, priority (eager + fetchpriority=high for LCP). Use the course or category cover with a brand tile fallback. --}}
@props(['course', 'priority' => false])
@php
    // Block form on purpose: mixing the inline php directive with a later php block in one file does not compile.
    $cover = $course->coverUrl();
@endphp
@if($cover)
    <img src="{{ $cover }}" alt="" width="640" height="360" @if($priority) loading="eager" fetchpriority="high" @else loading="lazy" @endif decoding="async" {{ $attributes->class('aspect-video w-full rounded-xl object-cover') }}>
@else
    @php
        $slug = strtolower((string) ($course->category?->slug_en ?? $course->slug_en ?? ''));
        $placeholderIcon = match (true) {
            str_contains($slug, 'draw') || str_contains($slug, 'art') || str_contains($slug, 'paint') => 'sparkle',
            str_contains($slug, 'voice') || str_contains($slug, 'audio') || str_contains($slug, 'sound') => 'play-circle',
            str_contains($slug, 'animat') || str_contains($slug, 'video') || str_contains($slug, 'film') => 'video',
            str_contains($slug, 'act') || str_contains($slug, 'theater') => 'users',
            str_contains($slug, 'design') || str_contains($slug, 'craft') => 'layers',
            default => 'book',
        };
        $placeholderTone = match (((int) $course->id) % 3) {
            0 => 'from-assistant to-accent text-primary',
            1 => 'from-nav-active-bg to-assistant text-nav-active-text',
            default => 'from-surface-muted to-assistant text-primary',
        };
    @endphp
    <div aria-hidden="true" {{ $attributes->class('grid aspect-video w-full place-items-center rounded-xl bg-gradient-to-br '.$placeholderTone) }}>
        <x-ui.icon :name="$placeholderIcon" class="size-12 opacity-75" />
    </div>
@endif
