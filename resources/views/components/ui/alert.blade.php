{{-- Props: tone. Pass role=alert for urgent dynamic feedback. --}}
@props(['tone' => 'info'])
@php($tones = ['info' => 'bg-info text-on-info', 'success' => 'bg-success text-on-success', 'warning' => 'bg-warning text-on-warning', 'danger' => 'bg-danger text-on-danger'])
<div {{ $attributes->class(['rounded-lg p-3 break-words', $tones[$tone] ?? $tones['info']]) }}>{{ $slot }}</div>
