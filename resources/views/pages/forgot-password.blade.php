<x-layouts.base variant="auth" :title="__('pages.forgot_title')">
    <h1 class="mb-4 text-center text-3xl font-bold">{{ __('pages.forgot_title') }}</h1>
    <x-ui.card>
        <p class="mb-4 text-muted">{{ __('pages.forgot_intro') }}</p>
        <x-ui.ajax-form :action="url('/forgot-password')" :message="__('pages.forgot_done')" reset>
            <x-ui.form-field for="email" :label="__('pages.email')" data-field><x-ui.input id="email" name="email" type="email" autocomplete="email" required /></x-ui.form-field>
            <x-ui.button type="submit" class="w-full">{{ __('pages.forgot_submit') }}</x-ui.button>
        </x-ui.ajax-form>
        <x-slot:footer><x-ui.link :href="url('/login')">{{ __('pages.back_to_login') }}</x-ui.link></x-slot:footer>
    </x-ui.card>
</x-layouts.base>
