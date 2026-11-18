@props(['links', 'mobile' => false])
<nav aria-label="{{ __('public-site.navigation') }}" data-public-nav {{ $attributes->class($mobile ? 'flex flex-col gap-1' : 'hidden items-center gap-3 lg:flex') }}>
    @foreach($links as [$href, $label, $active])
        <a href="{{ $href }}" @if($active) aria-current="page" @endif
            @class(['inline-flex min-h-11 items-center rounded-full px-4 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', 'bg-assistant text-primary' => $active, 'text-body hover:bg-page' => ! $active])>{{ $label }}</a>
    @endforeach
</nav>
