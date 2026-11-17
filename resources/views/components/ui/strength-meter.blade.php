{{-- Props: for (input id). Live password strength under a field; the input needs data-strength. Pure guidance; the server rules still decide. --}}
@props(['for'])
@php
    $labels = [__('pages.strength_weak'), __('pages.strength_fair'), __('pages.strength_good'), __('pages.strength_strong')];
@endphp
<div data-strength-meter="{{ $for }}" hidden class="space-y-1" data-labels="{{ json_encode($labels, JSON_UNESCAPED_UNICODE) }}">
    <progress max="4" value="0" aria-label="{{ __('pages.strength_label') }}" class="h-2 w-full accent-primary"></progress>
    <p class="text-sm text-muted" role="status" aria-live="polite" data-strength-text></p>
</div>
