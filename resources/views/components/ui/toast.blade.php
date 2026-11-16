{{-- Props: tone. Slot supplies announcement text; caller decides when to display the notification. --}}
@props(['tone' => 'info'])
<div role="status" {{ $attributes->class('flex items-center justify-between gap-3 rounded-lg border border-border bg-surface px-3 py-2 text-body') }}>
    <div><x-ui.badge :tone="$tone">{{ $slot }}</x-ui.badge></div>
    <x-ui.button variant="ghost" size="sm" data-ui-dismiss :aria-label="__('components.dismiss')"><x-ui.icon name="close" /></x-ui.button>
</div>
