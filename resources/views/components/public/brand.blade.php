@props(['href'])
<a href="{{ $href }}" data-public-brand
    {{ $attributes->class('inline-flex min-h-11 shrink-0 items-center gap-2 rounded-md text-xl leading-none font-extrabold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary sm:text-2xl') }}>
    <x-ui.icon name="book" class="size-8 text-primary sm:size-9"/>
    <span class="tracking-tight" lang="en" dir="ltr">Young Go</span>
</a>
