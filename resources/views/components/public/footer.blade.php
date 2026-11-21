@props(['home', 'links', 'pages', 'socialLinks'])
@php
    $locale = app()->getLocale();
    $linkClass = 'inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-body wrap-anywhere hover:text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp
<footer class="border-t border-border bg-surface" data-public-footer>
    <div class="mx-auto">
        <div class="site-footer-grid grid grid-cols-2">
            <div class="col-span-2 space-y-2">
                <x-public.brand :href="$home"/>
                <p class="max-w-xs leading-relaxed text-muted">{{ __('public-site.footer_tagline') }}</p>
            </div>
            <nav aria-labelledby="public-footer-discover">
                <h2 id="public-footer-discover" class="mb-1 font-bold">{{ __('public-site.quick_links') }}</h2>
                <ul>
                    @foreach($links as [$href, $label])
                        <li><a href="{{ $href }}" class="{{ $linkClass }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <nav aria-labelledby="public-footer-help">
                <h2 id="public-footer-help" class="mb-1 font-bold">{{ __('public-site.help') }}</h2>
                <ul>
                    <li><a href="{{ \App\Domain\Seo\PublicUrl::contact($locale) }}" class="{{ $linkClass }}">{{ __('public-site.contact') }}</a></li>
                    @foreach($pages->toBase()->only(['faq', 'privacy', 'terms', 'refund']) as $page)
                        <li><a href="{{ \App\Domain\Seo\PublicUrl::page($page, $locale) }}" class="{{ $linkClass }}">{{ $page->title }}</a></li>
                    @endforeach
                    <li><a href="{{ route('consent.show') }}" class="{{ $linkClass }}">{{ __('consent.settings_link') }}</a></li>
                </ul>
            </nav>
            <div class="site-footer-social">
                @if($socialLinks->isNotEmpty())
                    <ul>
                        @foreach($socialLinks as $platform => $href)
                            <li><a href="{{ $href }}" aria-label="{{ __('public-site.'.$platform) }}"><x-ui.icon :name="$platform"/></a></li>
                        @endforeach
                    </ul>
                @endif
                <p class="text-xs text-muted"><bdi>&copy; {{ now()->year }} Young Go</bdi> {{ __('public-site.rights') }}</p>
            </div>
        </div>
    </div>
</footer>
