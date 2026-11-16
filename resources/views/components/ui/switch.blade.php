{{-- Props: id, label. Native checked, disabled, name and value are forwarded. --}}
@props(['id', 'label'])
<label for="{{ $id }}" class="inline-flex min-h-11 items-center gap-3 rounded-md">
    <input id="{{ $id }}" type="checkbox" role="switch" {{ $attributes->class('peer sr-only') }}>
    <span aria-hidden="true" class="relative h-6 w-11 shrink-0 rounded-full border border-border bg-page before:absolute before:inset-y-0 before:start-0 before:m-0.5 before:size-5 before:rounded-full before:bg-muted peer-checked:border-primary peer-checked:bg-primary peer-checked:before:start-5 peer-checked:before:bg-on-primary peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary peer-disabled:opacity-50"></span>
    <span>{{ $label }}</span>
</label>
