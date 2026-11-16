{{-- Props: value, optional max (defaults to 5). Read-only rating. --}}
@props(['value', 'max' => 5])
<span role="img" aria-label="{{ __('components.rating', ['value' => $value, 'max' => $max]) }}" {{ $attributes->class('inline-flex items-center gap-2 font-semibold') }}><span aria-hidden="true">â˜…</span><bdi dir="ltr" aria-hidden="true">{{ $value }} / {{ $max }}</bdi></span>
