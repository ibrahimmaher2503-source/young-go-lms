{{-- Props: variant public/auth/student/parent, title, minimal (error pages: no session-dependent chrome), wide (public pages that need more width), indexable (public pages that fill the head slot with their own meta). --}}
@props(['variant' => 'public', 'title' => null, 'minimal' => false, 'wide' => false, 'indexable' => false, 'full' => false, 'homepage' => false, 'homeDisciplines' => false, 'localeAlternates' => [], 'authCard' => false])
@php
    $locale = app()->getLocale();
    $user = $minimal ? null : auth()->user();
    $child = ($user && ! $minimal) ? (app(\App\Domain\Identity\ActingLearner::class)->isParentContext() ? app(\App\Domain\Identity\ActingLearner::class)->current() : null) : null;
    $name = $user ? ($user->display_name ?: $user->name) : null;
    $public = $variant === 'public' && ! $minimal;
    $publicIdentity = $variant === 'public' || $authCard;
    if ($public) {
        $sitePages = app(\App\Domain\Content\Queries\PublicSitePages::class);
        $publicPages = $sitePages();
        $home = \App\Domain\Seo\PublicUrl::home($locale);
        $catalog = \App\Domain\Seo\PublicUrl::catalog($locale);
        $exploreNav = [
            [$catalog, __('public-site.courses'), request()->routeIs('catalog.index', 'public.catalog.index'), 'book'],
        ];
        $platformNav = [];
        if ($about = $publicPages->get('about')) {
            $platformNav[] = [\App\Domain\Seo\PublicUrl::page($about, $locale), __('public-site.about_nav'), request()->route('slug') === $about->slug, 'help'];
        }
        $platformNav[] = [\App\Domain\Seo\PublicUrl::contact($locale), __('public-site.contact'), request()->routeIs('public.contact.show', 'contact.show'), 'mail'];
        $publicNav = [...$exploreNav, ...$platformNav];
        if ($homepage ? $homeDisciplines : $sitePages->hasHomeDisciplines()) {
            $exploreNav[] = [$home.'#home-disciplines', __('public-site.disciplines_nav'), false];
        }
        if ($sitePages->hasHomeInstructors(app(\App\Domain\Instructors\Queries\PublicInstructors::class))) {
            $exploreNav[] = [$home.'#home-instructors', __('public-site.instructors_nav'), false];
        }
        $exploreNav[] = [\App\Domain\Seo\PublicUrl::blog($locale), __('blog.title'), request()->routeIs('public.blog.index', 'blog.index')];
    }
    $nav = match ($variant) {
        'parent' => [['/family', __('pages.nav_family')], ['/my', __('pages.nav_learning')], ['/profile', __('pages.nav_profile')]],
        'student' => array_values(array_filter([['/my', __('pages.nav_learning')], ['/my/courses', __('pages.nav_courses')], ($user && \App\Domain\Identity\GuardianAccess::isMinor($user)) ? ['/link', __('pages.nav_link')] : null, ['/profile', __('pages.nav_profile')]])),
        default => [],
    };
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" @if($publicIdentity) class="public-identity" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($indexable){{ $head ?? '' }}@else<meta name="robots" content="noindex, nofollow">@endif
    <title>{{ $title ? $title.' · ' : '' }}{{ __('app.name') }}</title>
    @if($publicIdentity)<link rel="icon" type="image/png" href="{{ asset('images/young-go-favicon.png') }}">@endif
    {{-- Primary face for the page language; the other subset loads on demand via unicode-range. crossorigin is required for font preloads. --}}
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ \Illuminate\Support\Facades\Vite::asset('resources/fonts/noto-sans-arabic/NotoSansArabic-'.($locale === 'ar' ? 'arabic' : 'latin').'.woff2') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-consent.tags :variant="$variant" :minimal="$minimal" :parent="$child !== null" />
</head>
<body @class(['min-h-screen bg-page font-sans text-body', 'flex flex-col public-site-shell' => $public, 'public-home-shell' => $homepage, 'public-auth-shell' => $authCard, 'public-error-shell' => $minimal && $variant === 'public'])
    @if($public) data-public-shell @endif
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
@unless($authCard)
<header class="border-b border-border bg-surface" @if($public) data-public-header @endif>
    <div @class([
        'mx-auto flex items-center justify-between px-4 py-2 sm:px-8',
        'site-header-inner' => $public,
        'min-h-[4.25rem] gap-2 xl:gap-4' => $public,
        'flex-wrap gap-x-4 gap-y-2' => ! $public,
        'max-w-[96rem]' => $public || $full,
        'max-w-5xl' => ! $public && ! $full,
    ])>
        <div @class(['site-header-leading flex items-center gap-5' => $public, 'flex flex-wrap items-center gap-3' => ! $public])>
            @if($public)
                <x-public.brand :href="$home" :aria-current="request()->routeIs('cms.home', 'public.home') ? 'page' : null"/>
                <x-public.navigation :links="$publicNav" class="site-header-nav"/>
            @elseif($publicIdentity)
                <x-public.brand :href="\App\Domain\Seo\PublicUrl::home($locale)"/>
            @else
                <a href="{{ $user ? url('/home') : url('/login') }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 text-xl font-extrabold text-primary" aria-label="{{ __('app.name') }}">@isset($minimalHeader)<x-ui.icon name="book" class="size-7 text-primary"/>@endisset<span dir="ltr">Young Go</span></a>
            @endif
            @isset($brandAside){{ $brandAside }}@endisset
        </div>
        @isset($minimalHeader){{ $minimalHeader }}@endisset
        @unless($minimal)
            @if(! $public && $nav !== [])
            <nav aria-label="{{ __('pages.main_nav') }}" class="flex flex-wrap items-center gap-x-4">
                @foreach($nav as [$href, $label])
                    <x-ui.link :href="url($href)" :aria-current="request()->is(ltrim($href, '/')) ? 'page' : null">{{ $label }}</x-ui.link>
                @endforeach
            </nav>
            @endif
            <div class="{{ $public ? 'site-header-actions flex shrink-0 items-center gap-0.5 sm:gap-1' : 'flex flex-wrap items-center gap-2' }}">
                <x-ui.theme-toggle class="{{ $public ? 'border-transparent! bg-transparent! text-muted! hover:bg-hover!' : '' }}"/>
                <x-ui.locale-switcher :locale="$locale" :alternates="$localeAlternates" :variant="$public ? 'ghost' : 'secondary'"/>
                @if($user)
                    <div class="{{ $public ? 'site-header-account ms-2 hidden max-w-48 xl:block' : 'contents' }}">
                        <x-ui.dropdown id="account-menu" :label="$name"><x-ui.account-actions/></x-ui.dropdown>
                    </div>
                @elseif($public)
                    <div class="site-header-auth ms-2 hidden items-center gap-2 border-s border-border ps-3 xl:flex">
                        <a href="{{ url('/register') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-body hover:bg-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('public-site.register') }}</a>
                        <a href="{{ url('/login') }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-on-primary hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"><x-ui.icon name="user" class="size-4"/>{{ __('auth.sign_in') }}</a>
                    </div>
                @elseif($variant !== 'auth')
                    <x-ui.link :href="url('/login')">{{ __('auth.sign_in') }}</x-ui.link>
                @endif
                @if($public)
                    <div class="site-header-drawer ms-1 xl:hidden">
                        <x-ui.drawer id="public-menu" :title="__('public-site.menu')" placement="side" trigger-size="sm" data-public-menu>
                            <x-slot:trigger><x-ui.icon name="menu"/><span class="sr-only">{{ __('public-site.menu') }}</span></x-slot:trigger>
                            <x-public.navigation :links="$publicNav" class="pb-5" mobile/>
                            <div class="border-t border-border pt-5">
                                @if($user)
                                    <x-ui.account-actions/>
                                @else
                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="{{ url('/login') }}" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-primary px-2 text-center text-sm font-bold text-on-primary hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('auth.sign_in') }}</a>
                                        <a href="{{ url('/register') }}" class="inline-flex min-h-12 items-center justify-center rounded-lg border border-border px-2 text-center text-sm font-semibold text-body hover:bg-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('public-site.register') }}</a>
                                    </div>
                                @endif
                            </div>
                        </x-ui.drawer>
                    </div>
                @endif
            </div>
        @endunless
    </div>
</header>
@endunless
<main id="main" tabindex="-1" class="mx-auto px-4 sm:px-8 {{ $public ? 'w-full flex-1' : '' }} {{ $full ? 'max-w-[96rem] py-6' : 'py-8' }} {{ $public && $full ? 'site-wide-content' : '' }} {{ $full ? '' : ($variant === 'auth' || $minimal ? 'max-w-md' : ($wide ? 'max-w-6xl' : 'max-w-5xl')) }}">
    {{ $slot }}
</main>
@if($public)
    @php($socialLinks = collect($siteSettings->socialLinks()))
    <x-public.footer :home="$home" :pages="$publicPages" :explore-links="$exploreNav" :social-links="$socialLinks"/>
@endif
<x-consent.banner :variant="$variant" :minimal="$minimal" :parent="$child !== null" />
</body>
</html>
