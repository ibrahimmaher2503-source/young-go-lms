{{-- Props: course. The cover is the category image when there is one, else a calm brand tile (course covers arrive with the course wizard). --}}
@props(['course'])
@if($course->category?->image && $course->category->status === 'active')
    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($course->category->image) }}" alt="" loading="lazy" {{ $attributes->class('aspect-video w-full rounded-xl object-cover') }}>
@else
    <div aria-hidden="true" {{ $attributes->class('grid aspect-video w-full place-items-center rounded-xl bg-gradient-to-br from-assistant to-accent text-primary') }}>
        <x-ui.icon name="book" class="size-12 opacity-70" />
    </div>
@endif
