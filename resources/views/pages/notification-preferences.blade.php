<x-layouts.learner :title="__('notification_events.prefs_title')">
    <h1 class="mb-2 text-3xl font-bold">{{ __('notification_events.prefs_title') }}</h1>
    <p class="mb-6 text-muted">{{ __('notification_events.prefs_intro') }}</p>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('notification_events.prefs_optional')">
            <x-ui.ajax-form :action="url('/profile/notifications')" method="PATCH" :message="__('pages.saved')">
                @foreach($overview['optional'] as $row)
                    <input type="hidden" name="enabled[{{ $row['event']->formKey() }}]" value="0">
                    <div>
                        <x-ui.switch id="pref_{{ $row['event']->formKey() }}" name="enabled[{{ $row['event']->formKey() }}]" value="1" :label="$row['event']->label()" :checked="$row['enabled']" />
                    </div>
                @endforeach

                @if($overview['phone'] !== [])
                    <h2 class="pt-2 text-lg font-semibold">{{ __('notification_events.prefs_phone') }}</h2>
                    @foreach($overview['phone'] as $channel)
                        <input type="hidden" name="phone[{{ $channel['channel'] }}]" value="0">
                        <div>
                            <x-ui.switch id="phone_{{ $channel['channel'] }}" name="phone[{{ $channel['channel'] }}]" value="1" :label="__('notification_events.phone_'.$channel['channel'])" :checked="$channel['enabled']" />
                        </div>
                    @endforeach
                @endif

                <p class="text-sm text-muted">
                    {{ $overview['email'] ? __('notification_events.prefs_email_on') : __('notification_events.prefs_email_off') }}
                    <a class="underline" href="{{ route('profile.page') }}">{{ __('notification_events.prefs_email_change') }}</a>
                </p>
                <x-ui.button type="submit">{{ __('pages.save') }}</x-ui.button>
            </x-ui.ajax-form>
        </x-ui.card>

        <x-ui.card :title="__('notification_events.prefs_mandatory')">
            <p class="mb-3 text-sm text-muted">{{ __('notification_events.prefs_mandatory_help') }}</p>
            <ul class="space-y-2">
                @foreach(collect($overview['mandatory'])->pluck('group')->unique() as $group)
                    <li>
                        <x-ui.checkbox id="always_{{ $group }}" :label="__('notification_events.groups.'.$group)" checked disabled />
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
</x-layouts.learner>
