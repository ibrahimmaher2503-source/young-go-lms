{{-- Props: course. Use the course or category cover with a brand tile fallback. --}}
@props(['course'])
@php($cover = $course->coverUrl())
@if($cover)
    <img src="{{ $cover }}" alt="" loading="lazy" {{ $attributes->class('aspect-video w-full rounded-xl object-cover') }}>
@else
    <div aria-hidden="true" {{ $attributes->class('grid aspect-video w-full place-items-center rounded-xl bg-gradient-to-br from-assistant to-accent text-primary') }}>
        <x-ui.icon name="book" class="size-12 opacity-70" />
    </div>
@endif
