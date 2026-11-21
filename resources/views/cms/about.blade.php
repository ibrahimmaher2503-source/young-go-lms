@php
    $paragraphs = preg_split('/\R{2,}/u', trim((string) $page->body)) ?: [];
    $features = ['layers', 'award', 'video', 'users'];
@endphp

<article class="info-page" data-info-page="about" aria-labelledby="info-page-title">
    <x-public.info-hero :title="$page->title" :description="__('public-info.about.intro')" image="about"/>

    <x-public.info-banner quiet :title="__('public-info.about.what')" :description="$paragraphs[0] ?? ''"
        image="images/public-info/about-band.webp"/>

    <section class="info-mission" aria-labelledby="info-mission-title">
        <h2 id="info-mission-title" class="info-section-title">{{ __('public-info.about.purpose') }}</h2>
        @foreach(['mission' => 'target', 'vision' => 'eye'] as $item => $icon)
            <div class="info-card info-card-row">
                <span class="info-icon"><x-ui.icon :name="$icon"/></span>
                <div>
                    <h3>{{ __('public-info.about.'.$item.'.title') }}</h3>
                    <p>{{ __('public-info.about.'.$item.'.body') }}</p>
                </div>
            </div>
        @endforeach
    </section>

    <section class="info-features" aria-labelledby="about-features-title">
        <h2 id="about-features-title" class="info-section-title">{{ __('public-info.about.features_title') }}</h2>
        @if(count($paragraphs) > 1)
            <details class="info-about-details">
                <summary>{{ __('public-info.about.details') }}</summary>
                @foreach(array_slice($paragraphs, 1) as $paragraph)
                    <p class="whitespace-pre-line" dir="auto">{{ $paragraph }}</p>
                @endforeach
            </details>
        @endif
        <div class="info-feature-grid">
            @foreach($features as $icon)
                <div class="info-card">
                    <span class="info-icon"><x-ui.icon :name="$icon"/></span>
                    <h3>{{ __('public-info.about.features.'.$icon.'.title') }}</h3>
                    <p>{{ __('public-info.about.features.'.$icon.'.body') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <x-public.info-banner :title="__('public-info.about.cta')" :description="__('public-info.about.cta_body')"
        image="images/public-info/catalog-band.webp">
        <x-public.info-course-link/>
    </x-public.info-banner>
</article>
