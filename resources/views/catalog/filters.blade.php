<form method="get" action="{{ route('catalog.index') }}" class="space-y-4">
    @if(isset($filters['q']))<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
    @foreach($options as $field => $choices)
        @if(count($choices) > 0)
            <div>
                <label for="catalog-{{ $prefix }}-{{ $field }}" class="mb-1 block font-semibold">{{ __('catalog.'.$field) }}</label>
                <x-ui.select id="catalog-{{ $prefix }}-{{ $field }}" name="{{ $field }}">
                    <option value="">{{ __('catalog.any') }}</option>
                    @foreach($choices as $key => $label)
                        <option value="{{ $key }}" @selected((string) ($filters[$field] ?? '') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        @endif
    @endforeach
    <div>
        <label for="catalog-{{ $prefix }}-age" class="mb-1 block font-semibold">{{ __('catalog.age') }}</label>
        <x-ui.input id="catalog-{{ $prefix }}-age" name="age" type="number" :value="$filters['age'] ?? ''" min="0"/>
    </div>
    <p class="text-sm">{{ __('catalog.age_priority') }}</p>
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button type="submit">{{ __('catalog.apply') }}</x-ui.button>
        <x-ui.link :href="route('catalog.index')">{{ __('catalog.clear') }}</x-ui.link>
    </div>
</form>
