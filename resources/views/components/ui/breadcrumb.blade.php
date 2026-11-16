{{-- Props: items (label/url; last item marked current). --}}
@props(['items'])
<nav aria-label="{{ __('components.breadcrumb') }}" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-2">
        @foreach($items as $item)
            <li class="flex items-center gap-2 break-words">
                @unless($loop->first)<x-ui.icon name="chevron" />@endunless
                @if($loop->last)<span aria-current="page">{{ $item['label'] }}</span>
                @else <x-ui.link :href="$item['url']">{{ $item['label'] }}</x-ui.link>@endif
            </li>
        @endforeach
    </ol>
</nav>
