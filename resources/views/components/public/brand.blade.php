@props(['href'])
<a href="{{ $href }}" data-public-brand
    {{ $attributes->class('inline-flex min-h-11 shrink-0 items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary') }}>
    <img src="{{ asset('images/young-go-logo.png') }}" alt="{{ __('app.name') }}" width="330" height="70">
</a>
