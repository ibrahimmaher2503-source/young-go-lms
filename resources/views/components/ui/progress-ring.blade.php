{{-- Props: label, value (0â€“100). --}}
@props(['label', 'value'])
@php($value = min(100, max(0, $value)))
<div role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value }}" {{ $attributes->class('relative grid size-16 place-items-center text-sm font-semibold') }}>
    <svg viewBox="0 0 40 40" class="absolute inset-0 size-full -rotate-90" fill="none" aria-hidden="true">
        <circle cx="20" cy="20" r="17" stroke-width="3" class="stroke-border"/>
        <circle cx="20" cy="20" r="17" pathLength="100" stroke-dasharray="{{ $value }} 100" stroke-width="3" class="stroke-primary"/>
    </svg>
    <bdi aria-hidden="true">{{ $value }}%</bdi>
</div>
