<x-filament-panels::page>
    <p class="text-sm">{{ __('settings.hub.intro') }}</p>
    <div class="yg-hub-grid" data-settings-hub>
        @forelse ($entries as $entry)
            <a href="{{ $entry['url'] }}" class="yg-hub-card" data-settings-entry="{{ $entry['key'] }}">
                <span class="yg-hub-icon"><x-filament::icon :icon="$entry['icon']" aria-hidden="true" /></span>
                <span class="yg-hub-text">
                    <span class="yg-hub-title">{{ $entry['label'] }}</span>
                    <span class="yg-hub-desc">{{ $entry['description'] }}</span>
                </span>
                <x-filament::icon icon="heroicon-m-chevron-right" class="yg-hub-chevron" aria-hidden="true" />
            </a>
        @empty
            <p data-settings-empty>{{ __('settings.hub.empty') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>