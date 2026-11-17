{{-- Props: variant, minimal, parent. Prints vendor tag scripts only on a marketing surface, with IDs set in settings, after the visitor accepted (R-021). Prints nothing otherwise. --}}
@props(['variant' => 'public', 'minimal' => false, 'parent' => false])
@php
    $consent = app(\App\Http\Consent\ConsentSurface::class)->resolve($variant, $minimal, $parent);
    $ga4 = $consent['tags']['ga4'];
    $pixel = $consent['tags']['meta_pixel'];
@endphp
@if($ga4)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ rawurlencode($ga4) }}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@js($ga4));</script>
@endif
@if($pixel)
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@js($pixel));fbq('track','PageView');</script>
@endif
