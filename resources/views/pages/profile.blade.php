<x-layouts.base :variant="$variant" :title="__('pages.profile_title')">
    <h1 class="mb-6 text-3xl font-bold">{{ __('pages.profile_title') }}</h1>
    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('pages.profile_names')">
            <x-ui.ajax-form :action="url('/profile')" method="PATCH" :message="__('pages.saved')">
                <x-ui.form-field for="name_ar" :label="__('pages.name_ar')" data-field><x-ui.input id="name_ar" name="name_ar" lang="ar" dir="rtl" value="{{ $user->name_ar }}" /></x-ui.form-field>
                <x-ui.form-field for="name_en" :label="__('pages.name_en')" data-field><x-ui.input id="name_en" name="name_en" lang="en" dir="ltr" value="{{ $user->name_en }}" /></x-ui.form-field>
                <x-ui.form-field for="display_name" :label="__('pages.display_name')" data-field><x-ui.input id="display_name" name="display_name" value="{{ $user->display_name }}" /></x-ui.form-field>
                <x-ui.form-field for="locale" :label="__('pages.locale')" data-field>
                    <x-ui.select id="locale" name="locale">
                        @foreach(config('app.supported_locales') as $code)
                            <option value="{{ $code }}" @selected(($user->locale ?? app()->getLocale()) === $code)>{{ __($code === 'ar' ? 'common.arabic' : 'common.english') }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.form-field>
                <x-ui.form-field for="timezone" :label="__('pages.timezone')" data-field>
                    <x-ui.select id="timezone" name="timezone">
                        @foreach($timezones as $zone)
                            <option value="{{ $zone }}" @selected($user->timezone === $zone)>{{ $zone }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.form-field>
                <input type="hidden" name="notification_preferences[email]" value="0">
                <div><x-ui.checkbox id="notify_email" name="notification_preferences[email]" value="1" :label="__('pages.notifications_email')" :checked="$emailNotifications" /></div>
                <x-ui.button type="submit">{{ __('pages.save') }}</x-ui.button>
            </x-ui.ajax-form>
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card :title="__('pages.certificate_names')">
                @if($user->certificate_names_locked_at)
                    <x-ui.alert tone="info" role="status">{{ __('profile.certificate_names_locked') }}</x-ui.alert>
                @else
                    <x-ui.ajax-form :action="url('/profile/certificate-names')" method="PATCH" :message="__('pages.saved')">
                        <x-ui.form-field for="certificate_name_ar" :label="__('pages.certificate_name_ar')" :help="__('pages.certificate_help')" data-field><x-ui.input id="certificate_name_ar" name="certificate_name_ar" lang="ar" dir="rtl" value="{{ $user->certificate_name_ar }}" aria-describedby="certificate_name_ar-help" /></x-ui.form-field>
                        <x-ui.form-field for="certificate_name_en" :label="__('pages.certificate_name_en')" data-field><x-ui.input id="certificate_name_en" name="certificate_name_en" lang="en" dir="ltr" value="{{ $user->certificate_name_en }}" /></x-ui.form-field>
                        <x-ui.button type="submit">{{ __('pages.save_certificate') }}</x-ui.button>
                    </x-ui.ajax-form>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('pages.avatar')">
                <x-ui.ajax-form :action="url('/profile/avatar')" files reload>
                    <x-ui.file-upload id="avatar" name="avatar" :label="__('pages.avatar')" :hint="__('pages.avatar_help')" accept="image/jpeg,image/png,image/webp" />
                    <x-ui.button type="submit" variant="secondary">{{ __('pages.upload') }}</x-ui.button>
                </x-ui.ajax-form>
            </x-ui.card>

            <x-ui.card :title="__('pages.change_password')">
                <x-ui.ajax-form :action="url('/profile/password')" method="PUT" :message="__('pages.password_changed')" reset>
                    <x-ui.form-field for="current_password" :label="__('pages.current_password')" data-field><x-ui.input id="current_password" name="current_password" type="password" autocomplete="current-password" required /></x-ui.form-field>
                    <x-ui.form-field for="new_password" :label="__('pages.new_password')" :help="__('pages.password_help')" data-field><x-ui.input id="new_password" name="password" type="password" autocomplete="new-password" aria-describedby="new_password-help" required /></x-ui.form-field>
                    <x-ui.form-field for="new_password_confirmation" :label="__('pages.password_confirmation')" data-field><x-ui.input id="new_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required /></x-ui.form-field>
                    <x-ui.button type="submit" variant="secondary">{{ __('pages.change_password') }}</x-ui.button>
                </x-ui.ajax-form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.base>
