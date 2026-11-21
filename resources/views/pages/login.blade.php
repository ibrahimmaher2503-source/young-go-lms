<x-layouts.base variant="auth" auth-card :title="__('auth.sign_in')">
    @if(session('session_notice'))
        <x-ui.alert tone="warning" role="alert" class="mb-4" data-session-notice>{{ session('session_notice') }}</x-ui.alert>
    @endif
    <x-public.auth-card :title="__('pages.login_title')">
        <p class="mb-5 text-center text-muted">{{ __('pages.login_intro') }}</p>
        <x-ui.ajax-form :action="url('/login')" :success="url('/home')">
            <x-ui.form-field for="login" :label="__('pages.login_label')" data-field>
                <x-ui.input id="login" name="login" dir="ltr" autocomplete="username" required />
            </x-ui.form-field>
            <x-ui.form-field for="password" :label="__('pages.password')" data-field>
                <x-ui.input id="password" name="password" type="password" autocomplete="current-password" required />
            </x-ui.form-field>
            <x-ui.button type="submit" class="w-full">{{ __('auth.sign_in') }}</x-ui.button>
        </x-ui.ajax-form>
        <x-slot:footer>
            <div class="flex flex-wrap justify-between gap-x-4">
                <x-ui.link :href="url('/forgot-password')">{{ __('pages.forgot_link') }}</x-ui.link>
                <x-ui.link :href="url('/register')">{{ __('pages.register_link') }}</x-ui.link>
            </div>
        </x-slot:footer>
    </x-public.auth-card>
</x-layouts.base>
