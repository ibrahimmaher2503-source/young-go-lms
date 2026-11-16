{{-- Props: locale (current locale). Uses POST and standard CSRF protection. --}}
@props(['locale' => app()->getLocale()])
<form method="post" action="{{ route('locale.update') }}" {{ $attributes }}>
    @csrf
    <x-ui.button type="submit" name="locale" :value="$locale === 'ar' ? 'en' : 'ar'" variant="secondary">{{ __('design.switch_language') }}</x-ui.button>
</form>
