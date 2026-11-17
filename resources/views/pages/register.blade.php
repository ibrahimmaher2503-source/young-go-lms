<x-layouts.base variant="auth" :title="__('pages.register_title')">
    <h1 class="mb-4 text-center text-3xl font-bold">{{ __('pages.register_title') }}</h1>
    <x-ui.card>
        @if(! $available)
            <x-ui.alert tone="warning" role="status">{{ __('auth.registration_unavailable') }}</x-ui.alert>
        @else
            <p class="mb-4 text-muted">{{ __('pages.register_intro') }}</p>
            <x-ui.ajax-form :action="url('/register')" :success="url('/email/verify')">
                <input type="hidden" name="terms_version" value="{{ $termsVersion }}">
                <input type="hidden" name="privacy_version" value="{{ $privacyVersion }}">
                <x-ui.form-field for="name_ar" :label="__('pages.name_ar')" data-field><x-ui.input id="name_ar" name="name_ar" lang="ar" dir="rtl" autocomplete="name" /></x-ui.form-field>
                <x-ui.form-field for="name_en" :label="__('pages.name_en')" data-field><x-ui.input id="name_en" name="name_en" lang="en" dir="ltr" autocomplete="name" /></x-ui.form-field>
                <x-ui.form-field for="email" :label="__('pages.email')" data-field><x-ui.input id="email" name="email" type="email" autocomplete="email" required /></x-ui.form-field>
                <x-ui.form-field for="username" :label="__('pages.username_optional')" :help="__('pages.username_help')" data-field><x-ui.input id="username" name="username" dir="ltr" autocomplete="username" aria-describedby="username-help" /></x-ui.form-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.form-field for="birth_year" :label="__('pages.birth_year')" data-field><x-ui.input id="birth_year" name="birth_year" type="number" inputmode="numeric" min="1900" max="{{ now()->year }}" /></x-ui.form-field>
                    <x-ui.form-field for="birth_month" :label="__('pages.birth_month')" data-field><x-ui.input id="birth_month" name="birth_month" type="number" inputmode="numeric" min="1" max="12" /></x-ui.form-field>
                </div>
                <x-ui.form-field for="password" :label="__('pages.password')" :help="__('pages.password_help')" data-field><x-ui.input id="password" name="password" type="password" autocomplete="new-password" aria-describedby="password-help" required /></x-ui.form-field>
                <x-ui.form-field for="password_confirmation" :label="__('pages.password_confirmation')" data-field><x-ui.input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required /></x-ui.form-field>
                <div data-field class="space-y-2">
                    <x-ui.checkbox id="accept_terms" name="accept_terms" value="1" :label="__('pages.accept_terms')" />
                    <x-ui.checkbox id="accept_privacy" name="accept_privacy" value="1" :label="__('pages.accept_privacy')" />
                </div>
                <x-ui.button type="submit" class="w-full">{{ __('pages.register_submit') }}</x-ui.button>
            </x-ui.ajax-form>
        @endif
        <x-slot:footer><x-ui.link :href="url('/login')">{{ __('pages.have_account') }}</x-ui.link></x-slot:footer>
    </x-ui.card>
</x-layouts.base>
