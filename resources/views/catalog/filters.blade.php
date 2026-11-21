@php
    $primaryIcons = ['category' => 'layers', 'age_band' => 'users', 'level' => 'level', 'format' => 'video', 'access' => 'book'];
    $additionalActive = array_diff_key($filters, $primaryIcons + ['q' => true, 'sort' => true]) !== [];
@endphp
<form method="get" action="{{ $catalogUrl() }}" class="space-y-3" data-filter-groups>
    @foreach(array_intersect_key($filters, ['q' => true, 'sort' => true]) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="rounded-xl border border-border bg-page/40">
        @foreach(array_intersect_key($options, $primaryIcons) as $field => $choices)
            @if($choices !== [])
                <x-ui.accordion :label="__('catalog.'.$field)" :icon="$primaryIcons[$field]" :open="isset($filters[$field])" data-filter-group="{{ $field }}" class="rounded-none! border-x-0! border-t-0! bg-transparent! text-base">
                    @include('catalog.filter-select')
                </x-ui.accordion>
            @endif
        @endforeach
        <x-ui.accordion :label="__('catalog.additional_filters')" icon="filter" :open="$additionalActive" data-filter-group="additional" class="rounded-none! border-0! bg-transparent! text-base">
            <div class="space-y-3 border-t border-border pt-3 text-sm">
                @foreach(array_diff_key($options, $primaryIcons) as $field => $choices)
                    @if($choices !== [])
                        <x-ui.form-field :for="'catalog-'.$prefix.'-'.$field" :label="__('catalog.'.$field)">
                            @include('catalog.filter-select')
                        </x-ui.form-field>
                    @endif
                @endforeach
                <fieldset class="min-w-0">
                    <legend class="mb-1 font-semibold">{{ __('catalog.price_range', ['currency' => $market->currency()]) }}</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['min', 'max'] as $bound)
                            @php($priceField = 'price_'.$bound)
                            <x-ui.form-field :for="'catalog-'.$prefix.'-price-'.$bound" :label="__('catalog.'.$priceField)">
                                <x-ui.input id="catalog-{{ $prefix }}-price-{{ $bound }}" name="{{ $priceField }}" type="number" :value="$filters[$priceField] ?? ''" min="0" max="1000000" step="any" inputmode="decimal"/>
                            </x-ui.form-field>
                        @endforeach
                    </div>
                </fieldset>
                <x-ui.form-field :for="'catalog-'.$prefix.'-age'" :label="__('catalog.age')" :help="__('catalog.age_priority')">
                    <x-ui.input id="catalog-{{ $prefix }}-age" name="age" type="number" :value="$filters['age'] ?? ''" min="0" aria-describedby="catalog-{{ $prefix }}-age-help"/>
                </x-ui.form-field>
            </div>
        </x-ui.accordion>
    </div>
    <div class="grid gap-2">
        <x-ui.button type="submit">{{ __('catalog.apply') }}</x-ui.button>
        <x-ui.link :href="$catalogUrl()" class="justify-center border border-border !no-underline">{{ __('catalog.clear') }}</x-ui.link>
    </div>
</form>
