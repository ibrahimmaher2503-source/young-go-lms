@php
    $locale = app()->getLocale();
    $sections = collect(preg_split('/\R{2,}/u', trim((string) $page->body)) ?: [])
        ->filter(fn ($text) => filled($text))->values()->map(function ($text) {
            [$first, $rest] = array_pad(preg_split('/\R/u', $text, 2) ?: [], 2, null);

            return ['title' => filled($rest) ? trim($first) : null, 'body' => filled($rest) ? trim($rest) : $text];
        });
@endphp

<article class="info-page" data-info-page="faq" aria-labelledby="info-page-title">
    <x-public.info-hero :title="$page->title" :description="$page->seo_description ?: __('public-info.faq.intro')" image="privacy"/>

    @if($sections->contains(fn ($section) => filled($section['title'])))
        <x-public.info-banner quiet :title="__('public-info.faq.help')" :description="__('public-info.faq.help_body')"
            image="images/public-info/about-band.webp" icon="help"/>
    @endif

    <x-public.info-contents :sections="$sections" prefix="faq" :heading="__('public-info.privacy.contents')" marker="help"
        :icons="['search', 'play-circle', 'calendar', 'book', 'users', 'award']"/>

    <x-public.info-banner :title="__('public-info.faq.contact')" :description="__('public-info.faq.contact_body')"
        image="images/public-info/contact-band.webp">
        <a class="info-banner-action me-3" href="{{ \App\Domain\Seo\PublicUrl::contact($locale) }}">{{ __('public-site.contact') }}<x-ui.icon name="arrow"/></a>
        <x-public.info-course-link/>
    </x-public.info-banner>
</article>
