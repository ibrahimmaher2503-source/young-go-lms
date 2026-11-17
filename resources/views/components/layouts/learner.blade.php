{{-- Learner shell (C01/C03/C24): brand, learning navigation, notifications, language, account; four-item bottom nav on phones.
     Props: title, wide (player uses the full width), back (optional [url, label] shown instead of the brand row on phones). --}}
@props(['title' => null, 'wide' => false, 'back' => null])
@php
    $locale = app()->getLocale();
    $user = auth()->user();
    $acting = app(\App\Domain\Identity\ActingLearner::class);
    $child = $user && $acting->isParentContext() ? $acting->current() : null;
    $name = $user ? ($user->display_name ?: $user->name) : '';
    $unread = $user ? $user->unreadNotifications()->exists() : false;
    $isParent = $user && $user->hasRole('parent');
    $items = $isParent ? [
        ['/parent', 'users', __('parent.nav'), request()->is('parent')],
        ['/my', 'book', __('pages.nav_learning'), request()->is('my') || request()->is('my/courses') || request()->is('learn/*')],
        ['/family', 'user', __('pages.nav_family'), request()->is('family')],
        ['/profile', 'user', __('pages.nav_profile'), request()->is('profile')],
    ] : [
        ['/my', 'book', __('pages.nav_learning'), request()->is('my') || request()->is('learn/*')],
        ['/my/courses', 'layers', __('pages.nav_courses'), request()->is('my/courses')],
        ['/link', 'user', __('pages.nav_link'), request()->is('link')],
        ['/profile', 'user', __('pages.nav_profile'), request()->is('profile')],
    ];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-page pb-24 font-sans text-body lg:pb-0"
    data-msg-error="{{ __('pages.error_generic') }}" data-msg-throttle="{{ __('pages.error_throttle') }}" data-msg-expired="{{ __('pages.error_expired') }}" data-msg-sending="{{ __('pages.sending') }}">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-lg focus:bg-surface focus:p-3">{{ __('pages.skip') }}</a>
@if($child)
    <div role="status" class="sticky top-0 z-40 border-b-4 border-primary bg-accent font-semibold text-on-accent shadow-md" data-child-context>
        <div class="mx-auto flex max-w-[90rem] flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-8">
            <p class="break-words font-semibold">{{ __('pages.child_context', ['name' => $child->display_name ?: $child->name]) }}</p>
            <x-ui.ajax-form :action="url('/guardian/context')" method="DELETE" reload class="!space-y-0">
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('pages.exit_child_context') }}</x-ui.button>
            </x-ui.ajax-form>
        </div>
    </div>
@endif
<header class="border-b border-border bg-surface/95 shadow-card backdrop-blur">
    <div class="mx-auto flex max-w-[90rem] items-center justify-between gap-3 px-4 py-2 sm:px-8">
        <div class="flex shrink-0 items-center gap-6">
            <a href="{{ url($isParent ? '/parent' : '/my') }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap text-xl font-extrabold text-body" aria-label="{{ __('app.name') }}">
                <x-ui.icon name="book" class="size-8 shrink-0 text-primary" />
                <span dir="ltr">Young Go</span>
            </a>
            <nav aria-label="{{ __('pages.main_nav') }}" class="hidden items-center gap-1 lg:flex">
                @foreach(array_slice($items, 0, 2) as [$href, $icon, $label, $active])
                    <a href="{{ url($href) }}" @if($active) aria-current="page" @endif
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg px-4 font-semibold {{ $active ? 'bg-nav-active-bg text-nav-active-text' : 'text-body hover:bg-hover' }}">
                        <x-ui.icon :name="$icon" /> <span>{{ $label }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        <div class="flex min-w-0 items-center gap-2">
            <x-ui.locale-switcher :locale="$locale" class="hidden sm:block" />
            <span class="relative inline-grid size-11 shrink-0 place-items-center text-body" title="{{ __('learner.notifications') }}">
                <x-ui.icon name="bell" class="size-6" />
                @if($unread)<span class="absolute end-2 top-2 size-2.5 rounded-full bg-primary" data-unread-dot></span>@endif
                <span class="sr-only">{{ __('learner.notifications') }}@if($unread): {{ __('learner.has_unread') }}@endif</span>
            </span>
            <x-ui.dropdown id="account-menu" :label="$name" class="min-w-56">
                <div class="flex flex-col gap-2">
                    <x-ui.link :href="url('/profile')">{{ __('pages.nav_profile') }}</x-ui.link>
                    <x-ui.link :href="url('/security/two-factor')">{{ __('pages.tf_title') }}</x-ui.link>
                    <x-ui.locale-switcher :locale="$locale" class="sm:hidden" />
                    <x-ui.ajax-form :action="url('/logout')" :success="url('/login')" class="!space-y-0">
                        <x-ui.button type="submit" variant="secondary" class="w-full">{{ __('auth.sign_out') }}</x-ui.button>
                    </x-ui.ajax-form>
                </div>
            </x-ui.dropdown>
        </div>
    </div>
</header>
<main id="main" tabindex="-1" class="mx-auto px-4 py-6 sm:px-8 sm:py-8 {{ $wide ? 'max-w-[90rem]' : 'max-w-6xl' }}">
    {{ $slot }}
</main>
<nav aria-label="{{ __('pages.main_nav') }}" class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-surface px-2 py-1 shadow-floating lg:hidden" data-bottom-nav>
    <ul class="mx-auto grid max-w-md grid-cols-4 gap-1">
        @foreach($items as [$href, $icon, $label, $active])
            <li>
                <a href="{{ url($href) }}" @if($active) aria-current="page" @endif
                    class="flex min-h-14 flex-col items-center justify-center gap-0.5 rounded-xl px-1 text-sm font-semibold {{ $active ? 'bg-nav-active-bg text-nav-active-text' : 'text-body' }}">
                    <x-ui.icon :name="$icon" class="size-6" /> <span class="break-words text-center">{{ $label }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
</body>
</html>
