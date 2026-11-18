<ul class="space-y-3">
    @forelse($resources as ['record' => $resource, 'preview' => $preview])
        <li class="flex flex-wrap items-center justify-between gap-3">
            <span dir="auto">{{ $resource->title }}</span>
            <div class="flex flex-wrap gap-2">
                @if($preview)
                    <x-filament::button tag="a" :href="$preview" target="_blank" rel="noopener noreferrer" color="gray">
                        {{ __('curriculum.preview_resource') }}
                    </x-filament::button>
                @endif
                <x-filament::button color="gray" wire:click="replaceMountedAction('editResource', { lesson: {{ $lesson->id }}, resource: {{ $resource->id }} })">
                    {{ __('curriculum.edit') }}
                </x-filament::button>
                @if($canDelete)
                    <x-filament::button color="danger" wire:click="replaceMountedAction('removeResource', { lesson: {{ $lesson->id }}, resource: {{ $resource->id }} })">
                        {{ __('curriculum.remove_resource') }}
                    </x-filament::button>
                @endif
            </div>
        </li>
    @empty
        <li>{{ __('curriculum.resources_empty') }}</li>
    @endforelse
</ul>
