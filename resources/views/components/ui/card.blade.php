{{-- Props: optional title; optional footer slot. --}}
@props(['title' => null])
<section {{ $attributes->class('min-w-0 rounded-xl border border-border bg-surface p-4 sm:p-6') }}>
    @if($title)<h2 class="mb-4 break-words text-lg font-bold">{{ $title }}</h2>@endif
    {{ $slot }}
    @isset($footer)<footer class="mt-4 border-t border-border pt-4">{{ $footer }}</footer>@endisset
</section>
