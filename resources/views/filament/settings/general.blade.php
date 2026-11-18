<x-filament-panels::page>
    @foreach ($rows as $section => $items)
        <x-filament::section :heading="__('settings.sections.'.$section)" data-settings-section="{{ $section }}">
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach ($items as $item)
                    <div data-field="{{ $item['key'] }}">
                        <dt class="text-sm font-medium">{{ $item['label'] }}</dt>
                        <dd><bdi>{{ $item['value'] ?? __('settings.none') }}</bdi></dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    @endforeach
    <div>
        <x-filament::button type="button" wire:click="mountAction('edit')" data-action="edit">{{ __('settings.edit') }}</x-filament::button>
    </div>
</x-filament-panels::page>
