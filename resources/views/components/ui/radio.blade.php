{{-- Props: id, label. Native checked/disabled/name/value are forwarded. --}}
@props(['id', 'label'])
<label for="{{ $id }}" class="inline-flex min-h-11 items-center gap-2 rounded-md">
    <input id="{{ $id }}" type="radio" {{ $attributes->class('size-5 shrink-0 accent-primary disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary') }}>
    <span>{{ $label }}</span>
</label>
