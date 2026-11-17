{{-- Props: name (check, close, chevron, spinner); decorative, label the parent control. --}}
@props(['name'])
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->class(['size-5 shrink-0', 'rtl:rotate-180' => $name === 'chevron']) }}>
    @switch($name)
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('close') <path d="m6 6 12 12M18 6 6 18"/> @break
        @case('download') <path d="M12 3v12m-5-5 5 5 5-5M4 16v4h16v-4"/> @break
        @case('chevron') <path d="m9 5 7 7-7 7"/> @break
        @case('spinner') <path d="M21 12a9 9 0 1 0-9 9"/> @break
    @endswitch
</svg>
