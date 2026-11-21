{{-- Props: name. Decorative: label the parent control. Directional icons (chevron, arrow, back) mirror in RTL. --}}
@props(['name'])
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->class(['size-5 shrink-0', 'rtl:rotate-180' => in_array($name, ['chevron', 'arrow', 'back', 'send'], true)]) }}>
    @switch($name)
        @case('grid') <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('globe') <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a16 16 0 0 1 0 18 16 16 0 0 1 0-18Z"/> @break
        @case('level') <path d="M5 20v-5m7 5V9m7 11V4"/> @break
        @case('tag') <path d="M3 11v-7h7l10 10-7 7zM7.5 8h.01"/> @break
        @case('filter') <path d="M3 5h18l-7 8v6l-4 2v-8z"/> @break
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
        @case('heart') <path d="M12 20.5s-8-4.6-8-10.4A4.6 4.6 0 0 1 12 7.5a4.6 4.6 0 0 1 8 2.6c0 5.8-8 10.4-8 10.4z"/> @break
        @case('home')<path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/> @break
        @case('sun') <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/> @break
        @case('moon') <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/> @break
        @case('star') <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/> @break
        @case('graduate') <path d="m2 9 10-5 10 5-10 5zm4 2v5l6 3 6-3v-5m4-2v6"/> @break
        @case('pencil') <path d="m4 15 11-11a2 2 0 0 1 3 0l2 2a2 2 0 0 1 0 3L9 20l-5 1zm9-9 5 5"/> @break
        @case('trophy') <path d="M7 3h10v7a5 5 0 0 1-10 0zm0 2H3v2a4 4 0 0 0 4 4m10-6h4v2a4 4 0 0 1-4 4m-5 4v5m-5 1h10"/> @break
        @case('gift') <path d="M4 10h16v11H4zM3 6h18v4H3zm9 0v15m0-15H8a2 2 0 1 1 2-3.6L12 6zm0 0h4a2 2 0 1 0-2-3.6L12 6z"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/> @break
        @case('phone') <path d="M22 16.9v3a2 2 0 0 1-2.2 2A18 18 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.5 2L7.8 10a14 14 0 0 0 6.2 6.2l1.3-1.3a2 2 0 0 1 2-.5l3 .5a2 2 0 0 1 1.7 2z"/> @break
        @case('lock') <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/> @break
        @case('list') <path d="M9 6h12M9 12h12M9 18h12M3 6h.01M3 12h.01M3 18h.01"/> @break
        @case('shield') <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m8.5 12 2.5 2.5 4.5-5"/> @break
        @case('eye') <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/> @break
        @case('target') <circle cx="11" cy="13" r="8"/><circle cx="11" cy="13" r="4"/><path d="m11 13 8-8m-1 1 .5-3L22 2l-1 3.5-3 .5"/> @break
        @case('cookie') <path d="M12 3a5 5 0 0 0 6 6 4 4 0 0 0 3 4 9 9 0 1 1-9-10zM8 9h.01M7 14h.01M12 16h.01M15 12h.01"/> @break
        @case('scale') <path d="M12 3v18m-5 0h10M4 6h16M6 6l-4 8h8L6 6Zm12 0-4 8h8l-4-8Z"/> @break
        @case('settings') <path d="m9 3 1 2h4l1-2 3 2v2l2 2 2 1v4l-2 1-2 2v2l-3 2-1-2h-4l-1 2-3-2v-2l-2-2-2-1v-4l2-1 2-2V5z"/><circle cx="12" cy="12" r="3"/> @break
        @case('youtube') <rect x="3" y="6" width="18" height="12" rx="3"/><path d="m10 9 5 3-5 3z" fill="currentColor"/> @break
        @case('instagram') <rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7h.01"/> @break
        @case('tiktok') <path d="M15 3v12a4 4 0 1 1-4-4m4-8a5 5 0 0 0 5 5"/> @break
        @case('x') <path d="M4 4l12 16h4L8 4zm16 0L4 20"/> @break
        @case('facebook') <path d="M14 21v-8h4l.5-4H14V7c0-1.2.8-2 2-2h3V2h-4a5 5 0 0 0-5 5v2H7v4h3v8"/> @break
        @case('linkedin') <path d="M4 9v11M4 4h.01M9 20V9m0 4a5 5 0 0 1 10 0v7"/> @break
    @endswitch
</svg>
