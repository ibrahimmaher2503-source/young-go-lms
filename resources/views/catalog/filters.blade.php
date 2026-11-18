<form method="get" action="{{ route('catalog.index') }}" class="space-y-2" data-filter-groups>
    @if(isset($filters['q']))<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
    @foreach($options as $field => $choices)
        @if(count($choices) > 0)
            <x-ui.accordion :label="__('catalog.'.$field)" :open="isset($filters[$field])" data-filter-group="{{ $field }}">
                <x-ui.select id="catalog-{{ $prefix }}-{{ $field }}" name="{{ $field }}" :aria-label="__('catalog.'.$field)">
                    <option value="">{{ __('catalog.any') }}</option>
                    @foreach($choices as $key => $label)
                        <option value="{{ $key }}" @selected((string) ($filters[$field] ?? '') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.accordion>
        @endif
    @endforeach
    <x-ui.accordion :label="__('catalog.price_range', ['currency' => $market->currency()])" :open="isset($filters['price_min']) || isset($filters['price_max'])" data-filter-group="price">
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
    </x-ui.accordion>
    <x-ui.accordion :label="__('catalog.sort')" :open="isset($filters['sort'])" data-filter-group="sort">
        <x-ui.select id="catalog-{{ $prefix }}-sort" name="sort" :aria-label="__('catalog.sort')">
            @foreach($sorts as $key => $label)
                <option value="{{ $key }}" @selected(($filters['sort'] ?? 'newest') === $key)>{{ $label }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.accordion>
    <x-ui.accordion :label="__('catalog.age')" :open="isset($filters['age'])" data-filter-group="age">
        <x-ui.input id="catalog-{{ $prefix }}-age" name="age" type="number" :value="$filters['age'] ?? ''" min="0" :aria-label="__('catalog.age')"/>
        <p class="mt-2 text-sm">{{ __('catalog.age_priority') }}</p>
    </x-ui.accordion>
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button type="submit">{{ __('catalog.apply') }}</x-ui.button>
        <x-ui.link :href="route('catalog.index')">{{ __('catalog.clear') }}</x-ui.link>
    </div>
</form>
