{{-- Props: id/name via attributes, type, invalid. Email, phone and URL use LTR. --}}
@props(['type' => 'text', 'invalid' => false])
<input type="{{ $type }}" @if(in_array($type, ['email', 'tel', 'url'], true)) dir="ltr" @endif
    @if($invalid) aria-invalid="true" @endif
    {{ $attributes->class(['min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-body disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', $invalid ? 'border-danger' : 'border-border']) }}>
