<x-filament-panels::page>
    <x-filament::section :heading="__('consent.admin.title')" :description="__('consent.admin.intro')" data-marketing-tags>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div data-tag="ga4">
                <dt class="text-sm font-medium">{{ __('consent.admin.fields.ga4_measurement_id') }}</dt>
                <dd><bdi>{{ $ga4 ?? __('consent.admin.none') }}</bdi></dd>
            </div>
            <div data-tag="meta_pixel">
                <dt class="text-sm font-medium">{{ __('consent.admin.fields.meta_pixel_id') }}</dt>
                <dd><bdi>{{ $pixel ?? __('consent.admin.none') }}</bdi></dd>
            </div>
        </dl>
        <p class="mt-4 text-sm">{{ __('consent.admin.version', ['version' => $version]) }}</p>
        <div class="mt-4">
            <x-filament::button type="button" wire:click="mountAction('edit')" data-action="edit">{{ __('consent.admin.edit') }}</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
