@php($filterIcons = ['category' => 'layers', 'age_band' => 'users', 'level' => 'level', 'format' => 'video', 'language' => 'globe', 'instructor' => 'user', 'access' => 'book', 'availability' => 'calendar'])
<form method="get" action="{{ $catalogUrl() }}" class="space-y-3" data-filter-groups>
    @foreach(array_intersect_key($filters, ['q' => true, 'sort' => true]) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="rounded-xl border border-border bg-page/40">
    @foreach($options as $field => $choices)
        @if(count($choices) > 0)
            <x-ui.accordion :label="__('catalog.'.$field)" :icon="$filterIcons[$field]" :open="isset($filters[$field])" data-filter-group="{{ $field }}" class="rounded-none! border-x-0! border-t-0! bg-transparent! text-base">
                <x-ui.select id="catalog-{{ $prefix }}-{{ $field }}" name="{{ $field }}" :aria-label="__('catalog.'.$field)">
                    <option value="">{{ __('catalog.any') }}</option>
                    @foreach($choices as $key => $label)
                        <option value="{{ $key }}" @selected((string) ($filters[$field] ?? '') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.accordion>
        @endif
    @endforeach
    <x-ui.accordion :label="__('catalog.price_range', ['currency' => $market->currency()])" icon="tag" :open="isset($filters['price_min']) || isset($filters['price_max'])" data-filter-group="price" class="rounded-none! border-x-0! border-t-0! bg-transparent! text-base">
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
    <x-ui.accordion :label="__('catalog.age')" icon="users" :open="isset($filters['age'])" data-filter-group="age" class="rounded-none! border-0! bg-transparent! text-base">
        <x-ui.input id="catalog-{{ $prefix }}-age" name="age" type="number" :value="$filters['age'] ?? ''" min="0" :aria-label="__('catalog.age')"/>
        <p class="mt-2 text-sm">{{ __('catalog.age_priority') }}</p>
    </x-ui.accordion>
    </div>
    <div class="grid gap-2">
        <x-ui.button type="submit">{{ __('catalog.apply') }}</x-ui.button>
        <x-ui.link :href="$catalogUrl()" class="justify-center border border-border !no-underline">{{ __('catalog.clear') }}</x-ui.link>
    </div>
</form>
