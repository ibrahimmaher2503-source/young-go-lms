{{-- Props: variant public/auth/student/parent, title, minimal (error pages: no session-dependent chrome). --}}
@props(['variant' => 'public', 'title' => null, 'minimal' => false])
@php
    $locale = app()->getLocale();
    $user = $minimal ? null : auth()->user();
    $child = ($user && ! $minimal) ? (app(\App\Domain\Identity\ActingLearner::class)->isParentContext() ? app(\App\Domain\Identity\ActingLearner::class)->current() : null) : null;
    $name = $user ? ($user->display_name ?: $user->name) : null;
    $nav = match ($variant) {
        'parent' => [['/family', __('pages.nav_family')], ['/profile', __('pages.nav_profile')]],
        'student' => [['/link', __('pages.nav_link')], ['/profile', __('pages.nav_profile')]],
        default => [],
    };
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
<body class="min-h-screen bg-page font-sans text-body"
    data-msg-error="{{ __('pages.error_generic') }}" data-msg-throttle="{{ __('pages.error_throttle') }}" data-msg-expired="{{ __('pages.error_expired') }}">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-lg focus:bg-surface focus:p-3">{{ __('pages.skip') }}</a>
@if($child)
    <div role="status" class="bg-accent text-on-accent" data-child-context>
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-8">
            <p class="font-semibold break-words">{{ __('pages.child_context', ['name' => $child->display_name ?: $child->name]) }}</p>
            <x-ui.ajax-form :action="url('/guardian/context')" method="DELETE" reload class="!space-y-0">
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('pages.exit_child_context') }}</x-ui.button>
            </x-ui.ajax-form>
        </div>
    </div>
@endif
<header class="border-b border-border bg-surface">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 sm:px-8">
        <a href="{{ $user ? url('/home') : url('/login') }}" class="inline-flex min-h-11 items-center text-xl font-extrabold text-primary" aria-label="{{ __('app.name') }}">{{ __('app.name') }}</a>
        @unless($minimal)
            <nav aria-label="{{ __('pages.main_nav') }}" class="flex flex-wrap items-center gap-x-4">
                @foreach($nav as [$href, $label])
                    <x-ui.link :href="url($href)" :aria-current="request()->is(ltrim($href, '/')) ? 'page' : null">{{ $label }}</x-ui.link>
                @endforeach
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.locale-switcher :locale="$locale" />
                @if($user)
                    <x-ui.dropdown id="account-menu" :label="$name">
                        <div class="flex flex-col gap-2">
                            <x-ui.link :href="url('/profile')">{{ __('pages.nav_profile') }}</x-ui.link>
                            <x-ui.link :href="url('/security/two-factor')">{{ __('pages.tf_title') }}</x-ui.link>
                            <x-ui.ajax-form :action="url('/logout')" :success="url('/login')" class="!space-y-0">
                                <x-ui.button type="submit" variant="secondary" class="w-full">{{ __('auth.sign_out') }}</x-ui.button>
                            </x-ui.ajax-form>
                        </div>
                    </x-ui.dropdown>
                @elseif($variant !== 'auth')
                    <x-ui.link :href="url('/login')">{{ __('auth.sign_in') }}</x-ui.link>
                @endif
            </div>
        @endunless
    </div>
</header>
<main id="main" tabindex="-1" class="mx-auto px-4 py-8 sm:px-8 {{ $variant === 'auth' || $minimal ? 'max-w-md' : 'max-w-5xl' }}">
    {{ $slot }}
</main>
</body>
</html>
