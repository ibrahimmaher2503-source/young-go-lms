{{-- Props: label, value (0â€“100). Null value is indeterminate. --}}
@props(['label', 'value' => null])
<progress aria-label="{{ $label }}" max="100" @if($value !== null) value="{{ min(100, max(0, $value)) }}" @endif {{ $attributes->class('h-3 w-full accent-primary') }}></progress>
