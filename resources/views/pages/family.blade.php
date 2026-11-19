<x-layouts.learner :title="__('pages.family_title')">
    <h1 class="text-3xl font-bold">{{ __('pages.family_title') }}</h1>
    <p class="mb-6 mt-1 text-muted">{{ __('pages.family_intro') }}</p>

    <div class="grid items-start gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('pages.children')" class="lg:col-span-2">
            @if($children->isEmpty())
                <x-ui.empty-state :title="__('pages.no_children_title')" :description="__('pages.no_children_desc')"><x-ui.link href="#add-child">{{ __('pages.add_child') }}</x-ui.link></x-ui.empty-state>
            @else
                <ul class="divide-y divide-border">
                    @foreach($children as $link)
                        @php($child = $link->child)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3" id="child-{{ $child->id }}">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-ui.avatar :name="$child->display_name ?: $child->name" />
                                <div class="min-w-0">
                                    <p class="break-words font-semibold">{{ $child->display_name ?: $child->name }}</p>
                                    <p class="text-sm text-muted" dir="ltr">{{ $child->username ?? $child->email }}</p>
                                </div>
                                <x-ui.badge tone="success">{{ __('pages.status_verified') }}</x-ui.badge>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <x-family.child-switcher :child="$child" />
                                <x-ui.button variant="ghost" data-copy-text="{{ __('pages.login_details', ['username' => $child->username, 'url' => url('/login')]) }}" data-copied="{{ __('pages.login_copied') }}">{{ __('pages.copy_login') }}</x-ui.button>
                                <x-family.child-password-reset :child="$child" />
                            </div>
                            <span data-copy-status role="status" class="w-full text-sm text-muted"></span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if($pending->isNotEmpty())
                <h3 class="mb-2 mt-6 font-semibold">{{ __('pages.pending_links') }}</h3>
                <ul class="space-y-2">
                    @foreach($pending as $link)
                        <li class="flex flex-wrap items-center justify-between gap-2">
                            <span class="break-words">{{ $link->child->display_name ?: $link->child->name }}</span>
                            <x-ui.badge tone="warning">{{ __('pages.pending_state') }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('guardian.verification.title')" id="guardian-verification">
            <p class="mb-4 text-muted">{{ __('guardian.verification.intro') }}</p>
            @if($guardianVerified)
                <x-ui.badge tone="success">{{ __('guardian.verification.verified') }}</x-ui.badge>
                <p class="mt-3" role="status">{{ __('guardian.verification.done') }}</p>
            @elseif(! $verified)
                <x-ui.alert tone="warning" role="status">{{ __('pages.verify_before_child') }}</x-ui.alert>
                <x-ui.link class="mt-3" :href="url('/email/verify')">{{ __('pages.verify_title') }}</x-ui.link>
            @else
                <x-ui.badge tone="warning">{{ __('guardian.verification.not_verified') }}</x-ui.badge>
                <x-ui.ajax-form class="mt-4" :action="url('/guardian/verification/code')" :success="url('/family')">
                    <x-ui.button type="submit" variant="{{ $otpPending ? 'ghost' : 'primary' }}">{{ $otpPending ? __('guardian.verification.resend') : __('guardian.verification.send') }}</x-ui.button>
                </x-ui.ajax-form>
                @if($otpPending)
                    <x-ui.ajax-form class="mt-4" :action="url('/guardian/verification')" :success="url('/family')">
                        <x-ui.form-field for="guardian_code" :label="__('guardian.verification.code')" :help="__('guardian.verification.code_help', ['minutes' => config('guardians.otp.expires_minutes')])" data-field><x-ui.input id="guardian_code" name="code" dir="ltr" inputmode="numeric" maxlength="6" autocomplete="one-time-code" aria-describedby="guardian_code-help" required /></x-ui.form-field>
                        <x-ui.button type="submit">{{ __('guardian.verification.confirm') }}</x-ui.button>
                    </x-ui.ajax-form>
                @endif
            @endif
        </x-ui.card>

        <x-ui.card :title="__('pages.add_child')" id="add-child">
            @unless($verified)
                <x-ui.alert tone="warning" role="status">{{ __('pages.verify_before_child') }}</x-ui.alert>
                <x-ui.link class="mt-3" :href="url('/email/verify')">{{ __('pages.verify_title') }}</x-ui.link>
            @else
            <p class="mb-4 text-muted">{{ __('pages.add_child_intro') }}</p>
            <x-ui.ajax-form :action="url('/guardian/children')" :success="url('/family')">
                <x-ui.form-field for="child_name_ar" :label="__('pages.name_ar')" data-field><x-ui.input id="child_name_ar" name="name_ar" lang="ar" dir="rtl" /></x-ui.form-field>
                <x-ui.form-field for="child_name_en" :label="__('pages.name_en')" data-field><x-ui.input id="child_name_en" name="name_en" lang="en" dir="ltr" /></x-ui.form-field>
                <x-ui.form-field for="child_username" :label="__('pages.username')" :help="__('pages.username_help')" data-field><x-ui.input id="child_username" name="username" dir="ltr" autocomplete="off" aria-describedby="child_username-help" required /></x-ui.form-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.form-field for="child_birth_year" :label="__('pages.birth_year')" data-field><x-ui.input id="child_birth_year" name="birth_year" type="number" inputmode="numeric" min="1900" max="{{ now()->year }}" required /></x-ui.form-field>
                    <x-ui.form-field for="child_birth_month" :label="__('pages.birth_month')" data-field><x-ui.month-select id="child_birth_month" name="birth_month" required /></x-ui.form-field>
                </div>
                <x-ui.form-field for="child_password" :label="__('pages.child_password')" :help="__('pages.password_help')" data-field><x-ui.input id="child_password" name="password" type="password" autocomplete="new-password" aria-describedby="child_password-help" data-strength required /><x-ui.strength-meter for="child_password" /></x-ui.form-field>
                <x-ui.form-field for="child_password_confirmation" :label="__('pages.password_confirmation')" data-field><x-ui.input id="child_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required /></x-ui.form-field>
                <x-ui.button type="submit">{{ __('pages.create_child') }}</x-ui.button>
            </x-ui.ajax-form>
            @endunless
        </x-ui.card>

        <x-ui.card :title="__('pages.redeem_title')">
            <p class="mb-4 text-muted">{{ __('pages.redeem_intro') }}</p>
            <x-ui.ajax-form :action="url('/guardian/links')" :success="url('/family')">
                <x-ui.form-field for="link_code" :label="__('pages.link_code')" data-field><x-ui.input id="link_code" name="code" dir="ltr" maxlength="16" autocomplete="off" autocapitalize="characters" required /></x-ui.form-field>
                <x-ui.button type="submit">{{ __('pages.redeem_submit') }}</x-ui.button>
            </x-ui.ajax-form>
        </x-ui.card>
    </div>
</x-layouts.learner>
