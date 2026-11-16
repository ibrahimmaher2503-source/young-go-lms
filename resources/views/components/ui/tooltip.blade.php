{{-- Props: id, text. Opens on hover or keyboard focus. Escape dismisses until focus or pointer returns. --}}
@props(['id', 'text'])
<span data-ui-tooltip class="group relative inline-flex max-w-full items-center">
    <x-ui.button size="sm" variant="ghost" :aria-describedby="$id" {{ $attributes }}>{{ $slot }}</x-ui.button>
    <span id="{{ $id }}" role="tooltip" class="absolute bottom-full start-0 z-10 hidden w-48 max-w-[70vw] rounded-md border border-border bg-surface p-2 text-sm text-body group-hover:block group-focus-within:block">{{ $text }}</span>
</span>
