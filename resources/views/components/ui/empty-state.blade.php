{{-- Props: title and description. Slot can contain primary next action. --}}
@props(['title', 'description'])
<div {{ $attributes->class('rounded-lg border border-dashed border-border p-6 text-center text-body') }}>
    <p class="font-semibold">{{ $title }}</p><p class="my-2 text-sm text-muted">{{ $description }}</p>{{ $slot }}
</div>
