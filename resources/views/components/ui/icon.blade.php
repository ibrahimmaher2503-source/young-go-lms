{{-- Props: name. Decorative: label the parent control. Directional icons (chevron, arrow, back) mirror in RTL. --}}
@props(['name'])
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->class(['size-5 shrink-0', 'rtl:rotate-180' => in_array($name, ['chevron', 'arrow', 'back', 'send'], true)]) }}>
    @switch($name)
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('close') <path d="m6 6 12 12M18 6 6 18"/> @break
        @case('download') <path d="M12 3v12m-5-5 5 5 5-5M4 16v4h16v-4"/> @break
        @case('chevron') <path d="m9 5 7 7-7 7"/> @break
        @case('chevron-down') <path d="m6 9 6 6 6-6"/> @break
        @case('spinner') <path d="M21 12a9 9 0 1 0-9 9"/> @break
        @case('book') <path d="M2 5.5C4.5 4 8 4 12 6c4-2 7.5-2 10-.5V19c-2.5-1.5-6-1.5-10 .5-4-2-7.5-2-10-.5z"/><path d="M12 6v13.5"/> @break
        @case('layers') <path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4m8-4v4"/> @break
        @case('award') <circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/> @break
        @case('bell') <path d="M6 16v-5a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 21a2 2 0 0 0 4 0"/> @break
        @case('user') <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/> @break
        @case('play') <path d="M8 5.5v13l11-6.5z" fill="currentColor"/> @break
        @case('play-circle') <circle cx="12" cy="12" r="9"/><path d="m10.5 8.5 5 3.5-5 3.5z"/> @break
        @case('arrow') <path d="M5 12h14m-6-6 6 6-6 6"/> @break
        @case('back') <path d="M19 12H5m6-6-6 6 6 6"/> @break
        @case('file') <path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6m-6 4h6"/> @break
        @case('clipboard') <rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4h6v3H9zm0 9 2 2 4-4"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('help') <circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7M12 17h.01"/> @break
        @case('sparkle') <path d="M12 3c.6 4.5 2.5 6.4 7 7-4.5.6-6.4 2.5-7 7-.6-4.5-2.5-6.4-7-7 4.5-.6 6.4-2.5 7-7z"/> @break
        @case('send') <path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"/> @break
        @case('video') <rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/> @break
        @case('more') <circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('users') <circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c2.4.7 4 2.2 4 5.2"/> @break
        @case('home') <path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/> @break
    @endswitch
</svg>
