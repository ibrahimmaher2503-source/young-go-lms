{{-- Props: locale (current locale). Uses POST and standard CSRF protection; returns to the current path. --}}
@props(['locale' => app()->getLocale()])
@php
    // A locale-prefixed public URL (/ar/..., /en/...) keeps its page and swaps the prefix; anything the redirect rule cannot carry falls back to that locale's home.
    $target = $locale === 'ar' ? 'en' : 'ar';
    $returnTo = preg_replace('#^/(ar|en)(?=/|$)#', '/'.$target, request()->getPathInfo());
    $returnTo = preg_match('#^/(?!/)[A-Za-z0-9/_.\-]*$#', $returnTo) ? $returnTo : (preg_match('#^/(ar|en)(?=/|$)#', request()->getPathInfo()) ? '/'.$target : '/');
@endphp
<form method="post" action="{{ route('locale.update') }}" {{ $attributes }}>
    @csrf
    <input type="hidden" name="return_to" value="{{ $returnTo }}">
    <x-ui.button type="submit" name="locale" :value="$locale === 'ar' ? 'en' : 'ar'" variant="secondary">{{ __('design.switch_language') }}</x-ui.button>
</form>
