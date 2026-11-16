{{-- Props: invalid, optional streaming slot. Supply id, rows and aria-describedby. --}}
@props(['invalid' => false])
<textarea @if($invalid) aria-invalid="true" @endif
    {{ $attributes->class(['min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-body disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', $invalid ? 'border-danger' : 'border-border']) }}>{{ $slot }}</textarea>
@isset($streaming)<div role="status" aria-live="polite" class="mt-2 whitespace-pre-wrap break-words rounded-lg bg-assistant p-3 text-body">{{ $streaming }}</div>@endisset
