<x-layouts.base variant="auth" minimal auth-card :title="__('pages.tf_challenge_title')">
    <x-public.auth-card :title="__('pages.tf_challenge_title')">
        <p class="mb-5 text-center text-muted">{{ __('pages.tf_challenge_intro') }}</p>
        <x-ui.ajax-form :action="url('/two-factor-challenge')" :success="url('/home')">
            <x-ui.form-field for="code" :label="__('pages.tf_code')" :help="__('pages.tf_code_help')" data-field>
                <x-ui.input id="code" name="code" dir="ltr" inputmode="numeric" autocomplete="one-time-code" aria-describedby="code-help" required />
            </x-ui.form-field>
            <x-ui.button type="submit" class="w-full">{{ __('pages.tf_verify') }}</x-ui.button>
        </x-ui.ajax-form>
        <x-slot:footer>
            <x-ui.ajax-form :action="url('/logout')" :success="url('/login')" class="!space-y-0">
                <x-ui.button type="submit" variant="ghost">{{ __('auth.sign_out') }}</x-ui.button>
            </x-ui.ajax-form>
        </x-slot:footer>
    </x-public.auth-card>
</x-layouts.base>
