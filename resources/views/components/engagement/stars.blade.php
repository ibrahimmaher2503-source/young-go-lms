{{-- Props: value (0-5, may be fractional), optional label. Read-only stars; the label is the accessible text. --}}
@props(['value', 'label' => null])
@php($filled = (int) round((float) $value))
<span role="img" aria-label="{{ $label ?? __('engagement.stars', ['value' => $value]) }}" {{ $attributes->class('inline-flex items-center gap-0.5') }}>
    @for($i = 1; $i <= 5; $i++)<span aria-hidden="true" class="{{ $i <= $filled ? 'text-star' : 'text-star-empty' }}">&#9733;</span>@endfor
</span>
