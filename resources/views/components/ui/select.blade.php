{{-- Props: invalid. Supply native options in the slot. --}}
@props(['invalid' => false])
<select @if($invalid) aria-invalid="true" @endif {{ $attributes->class(['min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-body disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', $invalid ? 'border-danger' : 'border-border']) }}>{{ $slot }}</select>
