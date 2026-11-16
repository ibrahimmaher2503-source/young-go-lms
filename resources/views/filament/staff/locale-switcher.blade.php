<form method="post" action="{{ route('locale.update') }}">
    @csrf
    <input type="hidden" name="return_panel" value="{{ $panel }}">
    <x-filament::button type="submit" name="locale" :value="app()->isLocale('ar') ? 'en' : 'ar'" color="gray" size="sm">
        <span lang="{{ app()->isLocale('ar') ? 'en' : 'ar' }}">{{ app()->isLocale('ar') ? 'English' : 'العربية' }}</span>
    </x-filament::button>
</form>
