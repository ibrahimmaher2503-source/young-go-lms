<x-layouts.base variant="auth" auth-card :title="__('pages.forgot_title')">
    <x-public.auth-card :title="__('pages.forgot_title')">
        <p class="mb-5 text-center text-muted">{{ __('pages.forgot_intro') }}</p>
        <x-ui.ajax-form :action="url('/forgot-password')" :message="__('pages.forgot_done')" reset>
            <x-ui.form-field for="email" :label="__('pages.email')" data-field><x-ui.input id="email" name="email" type="email" autocomplete="email" required /></x-ui.form-field>
            <x-ui.button type="submit" class="w-full">{{ __('pages.forgot_submit') }}</x-ui.button>
        </x-ui.ajax-form>
        <x-slot:footer><x-ui.link :href="url('/login')">{{ __('pages.back_to_login') }}</x-ui.link></x-slot:footer>
    </x-public.auth-card>
</x-layouts.base>
