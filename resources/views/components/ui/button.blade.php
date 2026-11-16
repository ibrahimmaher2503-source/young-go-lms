{{-- Props: variant primary/secondary/ghost/danger, size sm/md/lg, type, loading, disabled, optional icon slot. --}}
@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'loading' => false, 'disabled' => false])
@php
    $variants = ['primary' => 'bg-primary text-on-primary', 'secondary' => 'border border-border bg-surface text-body', 'ghost' => 'text-primary', 'danger' => 'bg-danger text-on-danger'];
    $sizes = ['sm' => 'px-3 text-sm', 'md' => 'px-4', 'lg' => 'px-5 py-3 text-lg'];
@endphp
<button type="{{ $type }}" @disabled($loading || $disabled) @if($loading) aria-busy="true" @endif
    {{ $attributes->class(['inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-lg py-2 font-semibold disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]) }}>
    @if($loading)<x-ui.icon name="spinner" class="motion-safe:animate-spin" />@elseif(isset($icon)){{ $icon }}@endif
    {{ $slot }}
</button>
