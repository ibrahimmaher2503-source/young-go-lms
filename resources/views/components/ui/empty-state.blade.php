{{-- Props: title and description; optional icon (a ui.icon name, or a heroicon-* name inside Filament). Slot can contain primary next action. --}}
@props(['title', 'description', 'icon' => null])
<div {{ $attributes->class('yg-empty-state rounded-lg border border-dashed border-border p-6 text-center text-body') }}>
    @if($icon)
        <span class="yg-empty-state-icon" aria-hidden="true">
            @if(str_starts_with($icon, 'heroicon-'))<x-filament::icon :icon="$icon" />@else<x-ui.icon :name="$icon" />@endif
        </span>
    @endif
    <p class="font-semibold">{{ $title }}</p><p class="my-2 text-sm text-muted">{{ $description }}</p>{{ $slot }}
</div>
