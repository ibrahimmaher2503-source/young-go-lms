<x-filament-panels::page>
    <x-filament::section :heading="__('settings.payee.title')" :description="__('settings.payee.intro')" data-payee-settings>
        <dl>
            <dt class="text-sm font-medium">{{ __('settings.fields.manual_payee') }}</dt>
            <dd><bdi>{{ $payee ?? __('settings.none') }}</bdi></dd>
        </dl>
        <p class="mt-4 text-sm" data-source="{{ $source }}">{{ __('settings.payee.source.'.$source) }}</p>
        <div class="mt-4">
            <x-filament::button type="button" wire:click="mountAction('edit')" data-action="edit">{{ __('settings.edit') }}</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
