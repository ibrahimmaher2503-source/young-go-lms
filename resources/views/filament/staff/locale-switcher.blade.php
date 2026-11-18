<div class="yg-topbar-tools">
<form method="post" action="{{ route('locale.update') }}">
    @csrf
    <input type="hidden" name="return_panel" value="{{ $panel }}">
    <x-filament::button class="yg-locale-button" type="submit" name="locale" :value="app()->isLocale('ar') ? 'en' : 'ar'" color="gray" icon="heroicon-o-language" size="sm" :aria-label="__('common.language')">
        <span lang="{{ app()->isLocale('ar') ? 'en' : 'ar' }}">{{ app()->isLocale('ar') ? 'English' : 'العربية' }}</span>
    </x-filament::button>
</form>
@if(auth()->check())<span class="yg-account-name">{{ auth()->user()->display_name }}</span>@endif
</div>
