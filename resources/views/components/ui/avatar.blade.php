{{-- Props: name, optional src. Text fallback uses first letters from the supplied name. --}}
@props(['name', 'src' => null])
<span {{ $attributes->class('inline-grid size-11 shrink-0 place-items-center overflow-hidden rounded-full bg-assistant font-semibold text-body') }}>
    @if($src)<img src="{{ $src }}" alt="{{ $name }}" width="44" height="44" class="size-full object-cover">
    @else <span role="img" aria-label="{{ $name }}" dir="auto">{{ mb_substr(trim($name), 0, 2) }}</span>@endif
</span>
