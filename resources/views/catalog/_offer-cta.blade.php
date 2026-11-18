{{-- Props: type (plan|bundle), id, signedIn, label, signInLabel. One call-to-action to start a checkout, or to sign in first. --}}
@php($classes = 'inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary')
@if($signedIn)
    <a href="{{ route('checkout.product', ['type' => $type, 'id' => $id]) }}" data-cta="buy-{{ $type }}" class="{{ $classes }}">{{ $label }}</a>
@else
    <a href="{{ route('login.page') }}" data-cta="sign-in" class="{{ $classes }}">{{ $signInLabel }}</a>
@endif
