@props(['home', 'pages', 'exploreLinks', 'socialLinks'])
@php
    $locale = app()->getLocale();
    $contact = \App\Domain\Seo\PublicUrl::contact($locale);
    $platformLinks = [];
    if ($about = $pages->get('about')) {
        $aboutUrl = \App\Domain\Seo\PublicUrl::page($about, $locale);
        $platformLinks[] = [$aboutUrl, __('public-site.about_nav'), request()->route('slug') === $about->slug];
        $platformLinks[] = [$aboutUrl.'#about-features-title', __('public-site.features_nav'), false];
    }
    if (app(\App\Domain\Subscriptions\Queries\PublicPlans::class)->paidForMarket(\App\Domain\Shared\Markets\Market::Egypt)->isNotEmpty()) {
        $platformLinks[] = [\App\Domain\Seo\PublicUrl::plans($locale), __('subscriptions.plans_page_title'), request()->routeIs('public.plans.index', 'plans.index')];
    }
    $platformLinks[] = [$contact, __('public-site.contact'), request()->routeIs('public.contact.show', 'contact.show')];
    if ($faq = $pages->get('faq')) {
        $platformLinks[] = [\App\Domain\Seo\PublicUrl::page($faq, $locale), $faq->title, request()->route('slug') === $faq->slug];
    }
    $platformLinks[] = [$contact.'#contact-reasons-title', __('public-site.help_center'), false];
    $policies = ['privacy', 'terms', 'refund'];
    $linkClass = 'inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-body wrap-anywhere hover:text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp
<footer class="border border-border bg-surface" data-public-footer>
    <div class="mx-auto">
        <div class="site-footer-grid grid grid-cols-2">
            <div class="site-footer-brand col-span-2">
                <x-public.brand :href="$home"/>
                <p class="max-w-xs leading-relaxed text-muted">{{ __('public-site.footer_tagline') }}</p>
                @if($socialLinks->isNotEmpty())
                    <div class="site-footer-social">
                        <p>{{ __('public-site.follow_us') }}</p>
                        <ul>
                            @foreach($socialLinks as $platform => $href)
                                <li><a href="{{ $href }}" aria-label="{{ __('public-site.'.$platform) }}"><x-ui.icon :name="$platform"/></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <img class="site-footer-art" src="{{ asset('images/public-info/footer-study.webp') }}" width="639" height="426" alt="" loading="lazy" decoding="async">
            </div>
            @foreach(['explore' => [$exploreLinks, 'grid'], 'platform' => [$platformLinks, 'layers']] as $group => [$links, $icon])
                <nav aria-labelledby="public-footer-{{ $group }}">
                    <h2 id="public-footer-{{ $group }}"><x-ui.icon :name="$icon"/>{{ __('public-site.'.$group) }}</h2>
                    <ul>
                        @foreach($links as [$href, $label, $active])
                            <li><a href="{{ $href }}" @if($active) aria-current="page" @endif class="{{ $linkClass }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>
        <div class="site-footer-bottom">
            <ul>
                @foreach($policies as $key)
                    @if($page = $pages->get($key))
                        <li><a href="{{ \App\Domain\Seo\PublicUrl::page($page, $locale) }}" @if(request()->route('slug') === $page->slug) aria-current="page" @endif class="{{ $linkClass }}">{{ $page->title }}</a></li>
                    @endif
                @endforeach
                <li><a href="{{ route('consent.show') }}" @if(request()->routeIs('consent.show')) aria-current="page" @endif class="{{ $linkClass }}">{{ __('consent.title') }}</a></li>
            </ul>
            <p><bdi>&copy; {{ now()->year }} Young Go</bdi> {{ __('public-site.rights') }}</p>
        </div>
    </div>
</footer>
