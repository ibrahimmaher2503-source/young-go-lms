{{-- Props: dir auto/ltr/rtl. Slot remains HTML-escaped when a Blade expression is supplied. --}}
@props(['dir' => 'auto'])
<bdi dir="{{ in_array($dir, ['auto', 'ltr', 'rtl'], true) ? $dir : 'auto' }}" {{ $attributes }}>{{ $slot }}</bdi>
