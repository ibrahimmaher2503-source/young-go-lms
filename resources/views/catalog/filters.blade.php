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
    <fieldset class="min-w-0">
        <legend class="mb-1 block font-semibold">{{ __('catalog.price_range', ['currency' => $market->currency()]) }}</legend>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label for="catalog-{{ $prefix }}-price-min" class="mb-1 block text-sm">{{ __('catalog.price_min') }}</label>
                <x-ui.input id="catalog-{{ $prefix }}-price-min" name="price_min" type="number" :value="$filters['price_min'] ?? ''" min="0" max="1000000" step="any" inputmode="decimal"/>
            </div>
            <div>
                <label for="catalog-{{ $prefix }}-price-max" class="mb-1 block text-sm">{{ __('catalog.price_max') }}</label>
                <x-ui.input id="catalog-{{ $prefix }}-price-max" name="price_max" type="number" :value="$filters['price_max'] ?? ''" min="0" max="1000000" step="any" inputmode="decimal"/>
            </div>
        </div>
    </fieldset>
    <div>
        <label for="catalog-{{ $prefix }}-sort" class="mb-1 block font-semibold">{{ __('catalog.sort') }}</label>
        <x-ui.select id="catalog-{{ $prefix }}-sort" name="sort">
            @foreach($sorts as $key => $label)
                <option value="{{ $key }}" @selected(($filters['sort'] ?? 'newest') === $key)>{{ $label }}</option>
            @endforeach
        </x-ui.select>
    </div>
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
