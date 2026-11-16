{{-- Props: for, label, help, error. The input should reference generated FOR-help / FOR-error ids via aria-describedby. --}}
@props(['for', 'label', 'help' => null, 'error' => null])
<div {{ $attributes->class('space-y-1') }}>
    <label for="{{ $for }}" class="block font-semibold">{{ $label }}</label>
    {{ $slot }}
    @if($help)<p id="{{ $for }}-help" class="text-sm text-muted">{{ $help }}</p>@endif
    @if($error)<p id="{{ $for }}-error" class="rounded-md bg-danger px-2 py-1 text-sm text-on-danger">{{ $error }}</p>@endif
</div>
