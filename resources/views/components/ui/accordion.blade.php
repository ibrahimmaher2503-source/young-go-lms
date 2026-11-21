{{-- Props: label, open. Native details/summary. --}}
@props(['label', 'open' => false, 'icon' => null])
<details @if($open) open @endif {{ $attributes->class('group rounded-lg border border-border bg-surface px-3') }}>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 rounded-md py-3 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary [&::-webkit-details-marker]:hidden">
        <span class="inline-flex items-center gap-2">@if($icon)<x-ui.icon :name="$icon" class="size-4 text-muted"/>@endif{{ $label }}</span>
        <x-ui.icon name="chevron-down" class="size-4 shrink-0 text-muted transition-transform group-open:rotate-180" />
    </summary>
    <div class="pb-3">{{ $slot }}</div>
</details>
