{{-- Props: seo (PageSeo array). Meta, canonical, hreflang, Open Graph and JSON-LD for an indexable public page. Fill the layout's head slot with it. --}}
@props(['seo'])
@php
    $blocks = array_values(array_filter([...(isset($seo['jsonld']['@type']) ? [$seo['jsonld']] : []), ...($seo['extra_jsonld'] ?? [])]));
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
@endphp
<meta name="description" content="{{ $seo['description'] }}">
@if($seo['keywords'] !== '')<meta name="keywords" content="{{ $seo['keywords'] }}">@endif
<meta name="robots" content="{{ $seo['robots'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
@foreach($seo['alternates'] as $hreflang => $href)<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">@endforeach
@foreach($seo['og'] as $property => $content)<meta property="{{ $property }}" content="{{ $content }}">@endforeach
@foreach($seo['og_alternate'] as $alternate)<meta property="og:locale:alternate" content="{{ $alternate }}">@endforeach
@foreach($blocks as $block)<script type="application/ld+json">{!! json_encode($block, $flags) !!}</script>@endforeach
