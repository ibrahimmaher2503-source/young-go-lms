@props(['title', 'description' => null, 'image', 'parent' => null, 'cover' => null, 'coverAlt' => ''])
@php
    $breadcrumbs = [['label' => __('public-site.home'), 'url' => \App\Domain\Seo\PublicUrl::home(app()->getLocale())]];
    if ($parent) {
        $breadcrumbs[] = $parent;
    }
    $breadcrumbs[] = ['label' => $title];
@endphp

<header @class(['info-hero public-band public-band-header', 'info-hero-reference' => $image === 'privacy'])>
    <div>
        <x-ui.breadcrumb class="text-sm text-muted" :items="$breadcrumbs"/>
        <h1 class="public-hero-title info-hero-title" id="info-page-title">{{ $title }}</h1>
        @if(filled($description))<p class="info-hero-intro">{{ $description }}</p>@endif
    </div>
    <div class="public-hero-media info-hero-media">
        @if($cover)
            <img src="{{ $cover }}" width="1280" height="720" alt="{{ $coverAlt }}" fetchpriority="high" decoding="async">
        @else
            <img src="{{ asset('images/public-info/'.$image.'-640.webp') }}"
                srcset="{{ asset('images/public-info/'.$image.'-640.webp') }} 640w, {{ asset('images/public-info/'.$image.'-1200.webp') }} 1200w"
                sizes="(min-width: 768px) 57vw, 92vw" width="1200" height="600" alt="" fetchpriority="high" decoding="async">
        @endif
    </div>
</header>
