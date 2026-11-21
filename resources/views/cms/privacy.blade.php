@php
    $sections = collect(preg_split('/\R(?=##[ \t]+\S)/u', trim((string) $page->body)) ?: [])
        ->filter(fn ($text) => filled($text))->values()->map(function ($text) {
            preg_match('/\A##[ \t]+([^\r\n]+)(?:\R|\z)/u', $text, $heading);

            return ['title' => $heading[1] ?? null, 'body' => trim(substr($text, strlen($heading[0] ?? '')))];
        });
    $icons = ['book', 'layers', 'settings', 'users', 'cookie', 'shield', 'scale', 'mail'];
@endphp

<article class="info-page" data-info-page="privacy" aria-labelledby="info-page-title">
    <x-public.info-hero :title="$page->title" :description="$page->seo_description ?: __('public-info.privacy.intro')" image="privacy"/>

    <x-public.info-banner quiet :title="__('public-info.privacy.trust')" :description="__('public-info.privacy.trust_body')"
        image="images/public-info/privacy-band.webp"/>

    <x-public.info-contents :sections="$sections" prefix="policy" :heading="__('public-info.privacy.contents')" :icons="$icons" marker="lock"/>

    <x-public.info-banner :title="__('public-info.privacy.cta')" :description="__('public-info.privacy.cta_body')"
        image="images/public-info/catalog-band.webp">
        <x-public.info-course-link/>
    </x-public.info-banner>
</article>
