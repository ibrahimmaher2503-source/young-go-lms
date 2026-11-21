{{-- Props: locale (current locale). Uses POST and standard CSRF protection; returns to the current path. --}}
@props(['locale' => app()->getLocale(), 'variant' => 'secondary', 'alternates' => []])
@php
    // A locale-prefixed public URL (/ar/..., /en/...) keeps its page and swaps the prefix; anything the redirect rule cannot carry falls back to that locale's home.
    $target = $locale === 'ar' ? 'en' : 'ar';
    $canonicalPath = isset($alternates[$target]) ? parse_url($alternates[$target], PHP_URL_PATH) : null;
    $returnTo = $canonicalPath ?: preg_replace('#^/(ar|en)(?=/|$)#', '/'.$target, request()->getPathInfo());
    $returnTo = preg_match('#^/(?!/)[\pL\pM\pN/_.\-]*$#uD', rawurldecode($returnTo)) ? $returnTo : (preg_match('#^/(ar|en)(?=/|$)#', request()->getPathInfo()) ? '/'.$target : '/');
@endphp
<form method="post" action="{{ route('locale.update') }}" {{ $attributes }}>
    @csrf
    <input type="hidden" name="return_to" value="{{ $returnTo }}">
    <x-ui.button type="submit" name="locale" :value="$target" :variant="$variant" :size="$variant === 'ghost' ? 'sm' : 'md'">
        @if($variant === 'ghost')
            <span class="sr-only min-[360px]:not-sr-only">{{ __('design.switch_language') }}</span>
            <bdi class="min-[360px]:hidden" aria-hidden="true">{{ strtoupper($target) }}</bdi>
        @else
            {{ __('design.switch_language') }}
        @endif
    </x-ui.button>
</form>
