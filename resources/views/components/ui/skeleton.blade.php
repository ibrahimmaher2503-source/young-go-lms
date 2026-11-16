{{-- Props: label. One accessible loading announcement with a decorative placeholder. --}}
@props(['label' => __('components.loading')])
<div role="status" {{ $attributes->class('rounded-lg bg-page p-3') }}>
    <span class="sr-only">{{ $label }}</span><div aria-hidden="true" class="h-4 w-2/3 rounded-full bg-border motion-safe:animate-pulse"></div>
</div>
