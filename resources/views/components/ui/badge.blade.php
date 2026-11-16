{{-- Props: tone neutral/success/warning/danger/info. --}}
@props(['tone' => 'neutral'])
@php($tones = ['neutral' => 'bg-page text-body', 'success' => 'bg-success text-on-success', 'warning' => 'bg-warning text-on-warning', 'danger' => 'bg-danger text-on-danger', 'info' => 'bg-info text-on-info'])
<span {{ $attributes->class(['inline-flex max-w-full items-center rounded-full px-3 py-1 text-sm font-medium break-words', $tones[$tone] ?? $tones['neutral']]) }}>{{ $slot }}</span>
