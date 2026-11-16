{{-- Props: paginator (LengthAwarePaginator) or explicit links (label/url/current). --}}
@props(['paginator' => null, 'links' => []])
<nav aria-label="{{ __('components.pagination') }}" {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    @if($paginator && $paginator->hasPages())
        @if($paginator->previousPageUrl())<x-ui.link :href="$paginator->previousPageUrl()" rel="prev">{{ __('components.previous') }}</x-ui.link>@endif
        <bdi dir="ltr" aria-current="page">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</bdi>
        @if($paginator->nextPageUrl())<x-ui.link :href="$paginator->nextPageUrl()" rel="next">{{ __('components.next') }}</x-ui.link>@endif
    @else
        @foreach($links as $item)
            @if($item['current'] ?? false)<span aria-current="page" class="px-2">{{ $item['label'] }}</span>
            @else <x-ui.link :href="$item['url']">{{ $item['label'] }}</x-ui.link>@endif
        @endforeach
    @endif
</nav>
