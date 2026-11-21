<div class="flex flex-col gap-2">
    <x-ui.link :href="url('/profile')">{{ __('pages.nav_profile') }}</x-ui.link>
    <x-ui.link :href="url('/security/two-factor')">{{ __('pages.tf_title') }}</x-ui.link>
    <x-ui.ajax-form :action="url('/logout')" :success="url('/login')" class="!space-y-0">
        <x-ui.button type="submit" variant="secondary" class="w-full">{{ __('auth.sign_out') }}</x-ui.button>
    </x-ui.ajax-form>
</div>
