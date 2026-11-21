<x-layouts.base variant="auth" auth-card :title="__('pages.verify_title')">
    <x-public.auth-card :title="__('pages.verify_title')">
        <p class="mb-4">{{ __('pages.verify_intro', ['email' => $email]) }}</p>
        <x-ui.ajax-form :action="url('/email/verification-notification')" :message="__('pages.verify_sent')">
            <x-ui.button type="submit" variant="secondary" class="w-full">{{ __('pages.verify_resend') }}</x-ui.button>
        </x-ui.ajax-form>
        <x-slot:footer><x-ui.link :href="url('/home')">{{ __('pages.verify_continue') }}</x-ui.link></x-slot:footer>
    </x-public.auth-card>
</x-layouts.base>
