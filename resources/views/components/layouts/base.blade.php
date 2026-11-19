{{-- Props: variant public/auth/student/parent, title, minimal (error pages: no session-dependent chrome), wide (public pages that need more width), indexable (public pages that fill the head slot with their own meta). --}}
@props(['variant' => 'public', 'title' => null, 'minimal' => false, 'wide' => false, 'indexable' => false, 'full' => false])
@php
    $locale = app()->getLocale();
    $user = $minimal ? null : auth()->user();
    $child = ($user && ! $minimal) ? (app(\App\Domain\Identity\ActingLearner::class)->isParentContext() ? app(\App\Domain\Identity\ActingLearner::class)->current() : null) : null;
    $name = $user ? ($user->display_name ?: $user->name) : null;
    $public = $variant === 'public' && ! $minimal;
    if ($public) {
        $publicPages = app(\App\Domain\Content\Queries\PublicSitePages::class)();
        $home = \App\Domain\Seo\PublicUrl::home($locale);
        $catalog = \App\Domain\Seo\PublicUrl::catalog($locale);
        $publicNav = [
            [$home, __('public-site.home'), request()->routeIs('cms.home', 'public.home')],
            [$catalog, __('public-site.courses'), request()->routeIs('catalog.index', 'public.catalog.index')],
            [$catalog.'#catalog-filters', __('public-site.categories'), false],
        ];
        if ($about = $publicPages->get('about')) {
            $publicNav[] = [\App\Domain\Seo\PublicUrl::page($about, $locale), $about->title, request()->route('slug') === $about->slug];
        }
    }
    $nav = match ($variant) {
        'parent' => [['/family', __('pages.nav_family')], ['/my', __('pages.nav_learning')], ['/profile', __('pages.nav_profile')]],
        'student' => array_values(array_filter([['/my', __('pages.nav_learning')], ['/my/courses', __('pages.nav_courses')], ($user && \App\Domain\Identity\GuardianAccess::isMinor($user)) ? ['/link', __('pages.nav_link')] : null, ['/profile', __('pages.nav_profile')]])),
        default => [],
    };
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($indexable){{ $head ?? '' }}@else<meta name="robots" content="noindex, nofollow">@endif
    <title>{{ $title ? $title.' · ' : '' }}{{ __('app.name') }}</title>
    {{-- Primary face for the page language; the other subset loads on demand via unicode-range. crossorigin is required for font preloads. --}}
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/noto-sans-arabic/NotoSansArabic-'.($locale === 'ar' ? 'arabic' : 'latin').'.woff2') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-consent.tags :variant="$variant" :minimal="$minimal" :parent="$child !== null" />
</head>
<body class="min-h-screen bg-page font-sans text-body"
    data-msg-error="{{ __('pages.error_generic') }}" data-msg-throttle="{{ __('pages.error_throttle') }}" data-msg-expired="{{ __('pages.error_expired') }}" data-msg-sending="{{ __('pages.sending') }}">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-lg focus:bg-surface focus:p-3">{{ __('pages.skip') }}</a>
@unless($minimal)
    @php
        $siteSettings = app(\App\Domain\Settings\GeneralSettings::class);
        $bannerText = $siteSettings->banner_enabled ? ($locale === 'ar' ? ($siteSettings->banner_text_ar ?: $siteSettings->banner_text_en) : ($siteSettings->banner_text_en ?: $siteSettings->banner_text_ar)) : null;
    @endphp
    @if(filled($bannerText))
        <div role="status" class="border-b border-border bg-accent px-4 py-2 text-center text-sm font-semibold text-on-accent" data-site-banner>{{ $bannerText }}</div>
    @endif
@endunless
@if($child)
    <div role="status" class="sticky top-0 z-40 bg-accent font-semibold text-on-accent border-b-4 border-primary shadow-md" data-child-context>
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-8">
            <p class="font-semibold break-words">{{ __('pages.child_context', ['name' => $child->display_name ?: $child->name]) }}</p>
            <x-ui.ajax-form :action="url('/guardian/context')" method="DELETE" reload class="!space-y-0">
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('pages.exit_child_context') }}</x-ui.button>
            </x-ui.ajax-form>
        </div>
    </div>
@endif
<header class="border-b border-border bg-surface">
    <div class="mx-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 sm:px-8 {{ $public || $full ? 'max-w-[96rem]' : 'max-w-5xl' }}">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ $public ? $home : ($user ? url('/home') : url('/login')) }}" class="inline-flex min-h-11 items-center gap-2 text-xl font-extrabold {{ $public ? 'text-body' : 'text-primary' }}" aria-label="{{ __('app.name') }}">@if($public || isset($minimalHeader))<x-ui.icon name="book" class="size-7 text-primary" />@endif{{ __('app.name') }}</a>
            @isset($brandAside){{ $brandAside }}@endisset
        </div>
        @isset($minimalHeader){{ $minimalHeader }}@endisset
        @unless($minimal)
            @if($public)
                <x-public.navigation :links="$publicNav" />
            @elseif($nav !== [])
            <nav aria-label="{{ __('pages.main_nav') }}" class="flex flex-wrap items-center gap-x-4">
                @foreach($nav as [$href, $label])
                    <x-ui.link :href="url($href)" :aria-current="request()->is(ltrim($href, '/')) ? 'page' : null">{{ $label }}</x-ui.link>
                @endforeach
            </nav>
            @endif
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
                @elseif($public)
                    <a href="{{ url('/register') }}" class="hidden min-h-11 items-center rounded-lg px-2 font-semibold text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary lg:inline-flex">{{ __('pages.register_title') }}</a>
                    <a href="{{ url('/login') }}" class="hidden min-h-11 items-center gap-2 rounded-lg bg-primary px-4 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary lg:inline-flex">{{ __('auth.sign_in') }}<x-ui.icon name="arrow" /></a>
                @elseif($variant !== 'auth')
                    <x-ui.link :href="url('/login')">{{ __('auth.sign_in') }}</x-ui.link>
                @endif
                @if($public)
                    <div class="lg:hidden">
                        <x-ui.drawer id="public-menu" :title="__('public-site.navigation')" :trigger="__('public-site.menu')">
                            <x-public.navigation :links="$publicNav" mobile />
                            @unless($user)
                                <div class="mt-3 flex flex-wrap gap-4 border-t border-border pt-3">
                                    <x-ui.link :href="url('/login')">{{ __('auth.sign_in') }}</x-ui.link>
                                    <x-ui.link :href="url('/register')">{{ __('pages.register_title') }}</x-ui.link>
                                </div>
                            @endunless
                        </x-ui.drawer>
                    </div>
                @endif
            </div>
        @endunless
    </div>
</header>
<main id="main" tabindex="-1" class="mx-auto px-4 sm:px-8 {{ $full ? 'max-w-[96rem] py-6' : 'py-8' }} {{ $full ? '' : ($variant === 'auth' || $minimal ? 'max-w-md' : ($wide ? 'max-w-6xl' : 'max-w-5xl')) }}">
    {{ $slot }}
</main>
@if($public)
    <footer class="border-t border-border bg-surface">
        <div class="mx-auto flex max-w-[96rem] flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-8">
            <a class="inline-flex min-h-11 items-center gap-2 font-bold text-body" href="{{ $home }}"><x-ui.icon name="book" class="text-primary" />{{ __('app.name') }}</a>
            <nav aria-label="{{ __('public-site.footer') }}" class="flex flex-wrap items-center gap-x-5">
                <x-ui.link :href="route('contact.show')">{{ __('public-site.contact') }}</x-ui.link>
                @foreach($publicPages as $pageLink)
                    <x-ui.link :href="\App\Domain\Seo\PublicUrl::page($pageLink, $locale)">{{ $pageLink->title }}</x-ui.link>
                @endforeach
                @foreach($siteSettings->socialLinks() as $platform => $destination)
                    @if(is_string($destination) && str_starts_with($destination, 'https://') && filter_var($destination, FILTER_VALIDATE_URL))
                        <x-ui.link :href="$destination">{{ __('public-site.'.$platform) }}</x-ui.link>
                    @endif
                @endforeach
            </nav>
        </div>
    </footer>
@endif
<x-consent.banner :variant="$variant" :minimal="$minimal" :parent="$child !== null" />
</body>
</html>
