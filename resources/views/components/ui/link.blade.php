{{-- Props: href. Supply descriptive text. --}}
@props(['href'])
<a href="{{ $href }}" {{ $attributes->class('inline-flex min-h-11 items-center rounded-md font-semibold text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary') }}>{{ $slot }}</a>
