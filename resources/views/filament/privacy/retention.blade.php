<x-filament-panels::page>
    <x-filament::section :heading="__('privacy.retention_title')" :description="__('privacy.retention_intro')" data-retention-policies>
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr>
                        <th class="py-2 pe-4 text-start font-medium">{{ __('privacy.retention_col_class') }}</th>
                        <th class="py-2 pe-4 text-start font-medium">{{ __('privacy.retention_col_days') }}</th>
                        <th class="py-2 pe-4 text-start font-medium">{{ __('privacy.retention_col_default') }}</th>
                        <th class="py-2 text-start font-medium">{{ __('privacy.retention_col_last') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $key => $row)
                        <tr data-class="{{ $key }}">
                            <td class="py-2 pe-4">
                                {{ $row['class']->label() }}
                                @if($row['class']->isManagedElsewhere())
                                    <span class="block text-xs">{{ __('privacy.retention_managed_ai') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pe-4"><bdi>{{ $row['days'] === null ? __('privacy.retention_keep') : __('privacy.retention_days', ['count' => $row['days']]) }}</bdi></td>
                            <td class="py-2 pe-4"><bdi>{{ $row['default'] === null ? __('privacy.retention_keep') : __('privacy.retention_days', ['count' => $row['default']]) }}</bdi></td>
                            <td class="py-2"><bdi>{{ $row['last_pruned_at'] === null ? __('privacy.retention_never') : $row['last_pruned_at'].' ('.$row['last_pruned_count'].')' }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-sm">{{ __('privacy.retention_protected') }}</p>
        <div class="mt-4">
            <x-filament::button type="button" wire:click="mountAction('edit')" data-action="edit">{{ __('privacy.retention_edit') }}</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
