<section class="yg-dashboard-tools">
    <header><h2>{{ __('staff.quick_access') }}</h2><p>{{ __('staff.quick_access_description') }}</p></header>
    @if($resources)
        <div class="yg-dashboard-links">
            @foreach($resources as $resource)
                <a href="{{ $resource['url'] }}" class="yg-dashboard-link"><x-filament::icon :icon="$resource['icon']" aria-hidden="true" /><span>{{ $resource['label'] }}</span><x-filament::icon icon="heroicon-o-chevron-left" class="yg-directional-icon" aria-hidden="true" /></a>
            @endforeach
        </div>
    @else
        <div class="yg-dashboard-empty"><x-filament::icon icon="heroicon-o-window" aria-hidden="true" /><h3>{{ __('staff.workspace_empty_title') }}</h3><p>{{ __('staff.workspace_empty_description') }}</p></div>
    @endif
</section>
