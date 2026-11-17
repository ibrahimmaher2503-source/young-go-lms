<x-layouts.base variant="auth" :title="__('pages.reset_title')">
    <h1 class="mb-4 text-center text-3xl font-bold">{{ __('pages.reset_title') }}</h1>
    <x-ui.card>
        <x-ui.ajax-form :action="url('/reset-password')" :success="url('/login')">
            <input type="hidden" name="token" value="{{ $token }}">
            <x-ui.form-field for="email" :label="__('pages.email')" data-field><x-ui.input id="email" name="email" type="email" value="{{ $email }}" autocomplete="email" required :readonly="$email !== ''" /></x-ui.form-field>
            <x-ui.form-field for="password" :label="__('pages.new_password')" :help="__('pages.password_help')" data-field><x-ui.input id="password" name="password" type="password" autocomplete="new-password" aria-describedby="password-help" required /></x-ui.form-field>
            <x-ui.form-field for="password_confirmation" :label="__('pages.password_confirmation')" data-field><x-ui.input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required /></x-ui.form-field>
            <x-ui.button type="submit" class="w-full">{{ __('pages.reset_submit') }}</x-ui.button>
        </x-ui.ajax-form>
    </x-ui.card>
</x-layouts.base>
