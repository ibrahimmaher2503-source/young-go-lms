@props(['items' => []])

@if ($items)
    <dl class="yg-summary-strip">
        @foreach ($items as $item)
            <div class="yg-summary-item" data-tone="{{ $item['tone'] ?? 'primary' }}">
                @if (filled($item['icon'] ?? null))
                    <x-filament::icon :icon="$item['icon']" class="yg-summary-icon" aria-hidden="true" />
                @endif

                <dt class="yg-summary-label" title="{{ $item['label'] }}">{{ $item['label'] }}</dt>
                <dd class="yg-summary-value" data-workspace-value="{{ $item['value'] }}">{{ $item['value'] }}</dd>
            </div>
        @endforeach
    </dl>
@endif
