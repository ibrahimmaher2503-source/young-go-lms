<x-filament-panels::page>
    <p class="text-sm">{{ __('settings.hub.intro') }}</p>
    <div class="yg-hub-sections" data-settings-hub>
        @forelse ($groups as $group)
            <section class="yg-hub-section" aria-labelledby="settings-group-{{ $group['key'] }}">
                <h2 id="settings-group-{{ $group['key'] }}" class="yg-hub-section-title">{{ $group['label'] }}</h2>
                <div class="yg-hub-grid">
                    @foreach ($group['items'] as $entry)
                        <a href="{{ $entry['url'] }}" class="yg-hub-card" data-settings-entry="{{ $entry['key'] }}">
                            <span class="yg-hub-icon"><x-filament::icon :icon="$entry['icon']" aria-hidden="true" /></span>
                            <span class="yg-hub-text">
                                <span class="yg-hub-title">{{ $entry['label'] }}</span>
                                <span class="yg-hub-desc">{{ $entry['description'] }}</span>
                            </span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="yg-hub-chevron" aria-hidden="true" />
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <p data-settings-empty>{{ __('settings.hub.empty') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>