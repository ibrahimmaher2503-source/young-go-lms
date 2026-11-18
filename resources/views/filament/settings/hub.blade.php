<x-filament-panels::page>
    <p class="text-sm">{{ __('settings.hub.intro') }}</p>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-settings-hub>
        @forelse ($entries as $entry)
            <a href="{{ $entry['url'] }}" class="block" data-settings-entry="{{ $entry['key'] }}">
                <x-filament::section>
                    <div class="flex items-start gap-3">
                        <x-filament::icon :icon="$entry['icon']" class="h-6 w-6 shrink-0" aria-hidden="true" />
                        <div>
                            <div class="font-medium">{{ $entry['label'] }}</div>
                            <div class="text-sm">{{ $entry['description'] }}</div>
                        </div>
                    </div>
                </x-filament::section>
            </a>
        @empty
            <p data-settings-empty>{{ __('settings.hub.empty') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
