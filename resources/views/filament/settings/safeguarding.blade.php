<x-filament-panels::page>
    <x-filament::section :heading="__('settings.safeguarding.title')" :description="__('settings.safeguarding.intro')" data-safeguarding-recipients>
        <p class="text-sm">{{ __('settings.safeguarding.roles_note', ['roles' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', $roles)]) }}</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" data-safeguarding-explain>{{ __('settings.safeguarding.explain') }}</p>
        <h3 class="mt-4 text-sm font-medium">{{ __('settings.safeguarding.emails') }}</h3>
        @if($emails === [])
            <p class="mt-1 text-sm" data-safeguarding-empty>{{ __('settings.safeguarding.none') }}</p>
        @else
            <ul class="mt-1 list-disc ps-5 text-sm" data-safeguarding-list>
                @foreach($emails as $email)
                    <li><bdi dir="ltr">{{ $email }}</bdi></li>
                @endforeach
            </ul>
        @endif
        <div class="mt-4">
            <x-filament::button type="button" wire:click="mountAction('edit')" data-action="edit">{{ __('settings.edit') }}</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
