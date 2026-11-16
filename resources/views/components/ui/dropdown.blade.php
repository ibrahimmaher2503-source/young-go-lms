{{-- Props: id, label. Put links or buttons in the native popover. --}}
@props(['id', 'label'])
<x-ui.button variant="secondary" :popovertarget="$id">{{ $label }}</x-ui.button>
<div id="{{ $id }}" popover aria-label="{{ $label }}" {{ $attributes->class('m-auto max-w-[calc(100%-2rem)] rounded-lg border border-border bg-surface p-3 text-body shadow-lg') }}>{{ $slot }}</div>
