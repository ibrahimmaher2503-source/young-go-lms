{{-- Props: selected, disabled. Wire click to the caller; aria-pressed reflects state. --}}
@props(['selected' => false, 'disabled' => false])
<button type="button" aria-pressed="{{ $selected ? 'true' : 'false' }}" @disabled($disabled)
    {{ $attributes->class(['min-h-11 rounded-full border px-4 text-sm font-semibold disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', 'border-primary bg-primary text-on-primary' => $selected, 'border-border bg-surface text-body' => ! $selected]) }}>{{ $slot }}</button>
