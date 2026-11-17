@php($shell = auth()->user()->hasRole('parent') ? 'parent' : (auth()->user()->hasRole('student') ? 'student' : 'public'))
<x-layouts.base :variant="$shell" :title="__('pages.tf_title')">
    <h1 class="mb-2 text-3xl font-bold">{{ __('pages.tf_title') }}</h1>
    <p class="mb-6 text-muted">{{ __('pages.tf_intro') }}</p>

    @if($required && ! $enabled)
        <x-ui.alert tone="warning" role="status" class="mb-4">{{ __('pages.tf_required') }}</x-ui.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @if(! $enabled)
            <x-ui.card :title="__('pages.tf_scan_title')">
                <ol class="list-decimal space-y-2 ps-5">
                    <li>{{ __('pages.tf_step_app') }}</li>
                    <li>{{ __('pages.tf_step_scan') }}</li>
                    <li>{{ __('pages.tf_step_code') }}</li>
                </ol>
                <div class="my-4 inline-block rounded-lg bg-white p-2" role="img" aria-label="{{ __('pages.tf_qr_label') }}">{!! $qr !!}</div>
                <p class="text-sm text-muted">{{ __('pages.tf_manual') }}</p>
                <p><code dir="ltr" class="break-all font-semibold" data-two-factor-secret>{{ $secret }}</code></p>
            </x-ui.card>

            <x-ui.card :title="__('pages.tf_confirm_title')">
                <x-ui.ajax-form :action="url('/security/two-factor/confirm')" result="recovery-codes">
                    <x-ui.form-field for="code" :label="__('pages.tf_code')" :help="__('pages.tf_code_help')" data-field>
                        <x-ui.input id="code" name="code" dir="ltr" inputmode="numeric" autocomplete="one-time-code" aria-describedby="code-help" required />
                    </x-ui.form-field>
                    <x-ui.button type="submit">{{ __('pages.tf_enable') }}</x-ui.button>
                </x-ui.ajax-form>
                <div id="recovery-codes" hidden class="mt-4 rounded-lg bg-page p-4">
                    <h3 class="font-bold">{{ __('pages.tf_recovery_title') }}</h3>
                    <p class="mb-2 text-sm">{{ __('pages.tf_recovery_warning') }}</p>
                    <ul data-result-list tabindex="-1" dir="ltr" class="grid grid-cols-2 gap-2 font-mono text-lg"></ul>
                    <x-ui.link class="mt-4" :href="url('/home')">{{ __('pages.tf_continue') }}</x-ui.link>
                </div>
            </x-ui.card>
        @else
            <x-ui.card :title="__('pages.tf_enabled_title')">
                <x-ui.badge tone="success">{{ __('pages.tf_enabled') }}</x-ui.badge>
                <p class="mt-3 text-muted">{{ __('pages.tf_remaining', ['count' => $remaining]) }}</p>
            </x-ui.card>

            <x-ui.card :title="__('pages.tf_regenerate_title')">
                <x-ui.ajax-form :action="url('/security/two-factor/recovery-codes')" result="recovery-codes">
                    <x-ui.form-field for="regen-password" :label="__('pages.your_password')" data-field>
                        <x-ui.input id="regen-password" name="password" type="password" autocomplete="current-password" required />
                    </x-ui.form-field>
                    <x-ui.button type="submit" variant="secondary">{{ __('pages.tf_regenerate') }}</x-ui.button>
                </x-ui.ajax-form>
                <div id="recovery-codes" hidden class="mt-4 rounded-lg bg-page p-4">
                    <p class="mb-2 text-sm">{{ __('pages.tf_recovery_warning') }}</p>
                    <ul data-result-list tabindex="-1" dir="ltr" class="grid grid-cols-2 gap-2 font-mono text-lg"></ul>
                </div>
            </x-ui.card>

            @unless($required)
                <x-ui.card :title="__('pages.tf_disable_title')">
                    <p class="mb-4 text-muted">{{ __('pages.tf_disable_intro') }}</p>
                    <x-ui.ajax-form :action="url('/security/two-factor')" method="DELETE" :success="url('/security/two-factor')">
                        <x-ui.form-field for="disable-password" :label="__('pages.your_password')" data-field>
                            <x-ui.input id="disable-password" name="password" type="password" autocomplete="current-password" required />
                        </x-ui.form-field>
                        <x-ui.button type="submit" variant="danger">{{ __('pages.tf_disable') }}</x-ui.button>
                    </x-ui.ajax-form>
                </x-ui.card>
            @endunless
        @endif
    </div>
</x-layouts.base>
