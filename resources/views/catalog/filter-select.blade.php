<x-ui.select id="catalog-{{ $prefix }}-{{ $field }}" name="{{ $field }}" :aria-label="__('catalog.'.$field)">
    <option value="">{{ __('catalog.any') }}</option>
    @foreach($choices as $key => $label)
        <option value="{{ $key }}" @selected((string) ($filters[$field] ?? '') === (string) $key)>{{ $label }}</option>
    @endforeach
</x-ui.select>
