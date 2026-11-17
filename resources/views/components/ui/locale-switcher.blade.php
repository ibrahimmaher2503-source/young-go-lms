{{-- Props: locale (current locale). Uses POST and standard CSRF protection; returns to the current path. --}}
@props(['locale' => app()->getLocale()])
<form method="post" action="{{ route('locale.update') }}" {{ $attributes }}>
    @csrf
    <input type="hidden" name="return_to" value="{{ request()->getPathInfo() }}">
    <x-ui.button type="submit" name="locale" :value="$locale === 'ar' ? 'en' : 'ar'" variant="secondary">{{ __('design.switch_language') }}</x-ui.button>
</form>
