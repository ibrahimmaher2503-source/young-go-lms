@props(['links', 'mobile' => false])
<nav aria-label="{{ __('public-site.navigation') }}" data-public-nav {{ $attributes->class($mobile ? 'grid gap-1.5' : 'hidden items-center gap-1 border-s border-border ps-5 xl:flex') }}>
    @foreach($links as [$href, $label, $active, $icon])
        <a href="{{ $href }}" @if($active) aria-current="page" @endif
            @class(['flex items-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                'min-h-14 gap-3 rounded-xl px-3.5' => $mobile,
                'min-h-11 max-w-52 whitespace-nowrap rounded-lg px-3 text-base' => ! $mobile,
                'bg-nav-active-bg font-bold text-nav-active-text' => $active,
                'font-semibold text-body hover:bg-hover' => ! $active])>
            @if($mobile)
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-muted text-primary"><x-ui.icon :name="$icon"/></span>
            @endif
            <span class="{{ $mobile ? 'min-w-0 wrap-anywhere' : 'truncate' }}">{{ $label }}</span>
            @if($mobile)<x-ui.icon name="chevron" class="ms-auto size-4 text-muted"/>@endif
        </a>
    @endforeach
</nav>
