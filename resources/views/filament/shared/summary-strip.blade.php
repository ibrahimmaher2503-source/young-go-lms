@props(['items' => [], 'variant' => null])

@if ($items)
    <dl @class(['yg-summary-strip', 'is-tiles' => ($variant ?? null) === 'tiles'])>
        @foreach ($items as $item)
            <div class="yg-summary-item @if (filled($item['url'] ?? null)) is-link @endif" data-tone="{{ $item['tone'] ?? 'primary' }}">
                @if (filled($item['icon'] ?? null))
                    <x-filament::icon :icon="$item['icon']" class="yg-summary-icon" aria-hidden="true" />
                @endif

                <dt class="yg-summary-label" title="{{ $item['label'] }}">
                    @if (filled($item['url'] ?? null))
                        <a href="{{ $item['url'] }}" class="yg-summary-item-link">{{ $item['label'] }}</a>
                    @else
                        {{ $item['label'] }}
                    @endif
                </dt>
                <dd class="yg-summary-value" data-workspace-value="{{ $item['value'] }}">
                    @if ($item['ltr'] ?? false)
                        <bdi dir="ltr">{{ $item['value'] }}</bdi>
                    @else
                        {{ $item['value'] }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
@endif
